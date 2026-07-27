<?php

declare(strict_types=1);

namespace App\Domains\Matching\Http\Controllers\Api;

use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 근로자 앱 — 내 배정·입국 일정 (업무흐름 §4·§5).
 *
 * 근로자가 가장 먼저 궁금해하는 정보다: 어느 농가에, 언제 입국해서, 언제까지 일하는가.
 * 데이터는 이미 서버에 있었지만 근로자가 볼 경로가 없었다.
 *
 * 확정(confirmed)된 배정만 보여준다 — 제안(proposed) 단계는 아직 바뀔 수 있어
 * 근로자에게 알리면 혼선이 생긴다.
 *
 * 담당자 개인정보(픽업 담당자 이름·연락처)는 내려주지 않는다. 근로자에게 필요한
 * 것은 일정이고, 연락은 앱 채팅으로 한다(소통 일원화).
 */
class MyPlacementController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $placement = Placement::where('worker_id', $worker->id)
            ->where('status', PlacementStatus::Confirmed->value)
            ->with(['farm:id,name,address,main_crop,city_id', 'farm.city:id,name', 'arrival'])
            ->latest('id')
            ->first();

        // 아직 배정 전이면 data:null — 앱은 '배정 대기 중' 안내를 띄운다.
        if ($placement === null) {
            return response()->json(['data' => null]);
        }

        $arrival = $placement->arrival;

        return response()->json([
            'data' => [
                'farm' => $placement->farm?->name,
                'farm_address' => $placement->farm?->address,
                'crop' => $placement->farm?->main_crop,
                'city' => $placement->farm?->city?->name,
                'start_date' => $placement->start_date?->toDateString(),
                'end_date' => $placement->end_date?->toDateString(),
                'confirmed_at' => $placement->confirmed_at?->toIso8601String(),

                // 입국 일정 — 배정 확정 시 함께 만들어진다
                'arrival' => $arrival === null ? null : [
                    'status' => $arrival->status->value,
                    'status_label' => $arrival->status->label(),
                    'step' => $arrival->status->step(),
                    'total_steps' => count(ArrivalStatus::cases()),
                    'flight_no' => $arrival->flight_no,
                    'airport' => $arrival->airport,
                    'scheduled_arrival_at' => $arrival->scheduled_arrival_at?->toIso8601String(),
                    'arrived_at' => $arrival->arrived_at?->toIso8601String(),
                    'handed_over_at' => $arrival->handed_over_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}
