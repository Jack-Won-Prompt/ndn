<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Controllers\Api;

use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Monitoring\Models\LifeChecklistCheck;
use App\Domains\Monitoring\Models\LifeChecklistItem;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 근로자 앱 대시보드 — 로그인 후 첫 화면 (업무흐름 전반).
 *
 * "내 상태가 지금 어떤가" 를 한 번에 보여 준다. 화면을 옮겨 다니며 확인하지
 * 않아도 되도록 각 영역의 요약만 모은다.
 *
 * 인증된 Worker 본인에서만 파생하므로 스코프가 자동 보장된다(§9).
 * 상세는 각 화면이 따로 조회하고, 여기서는 요약 값만 내려준다.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $placement = Placement::where('worker_id', $worker->id)
            ->where('status', PlacementStatus::Confirmed->value)
            ->with(['farm:id,name', 'arrival'])
            ->latest('id')
            ->first();

        $arrival = $placement?->arrival;

        // 생활 체크리스트를 어디까지 확인했는지 — 홈에서 가장 자주 보는 값이다.
        // 월별 자가 평가(6항목)가 있던 자리다. 그쪽은 폐기됐다.
        $checklistTotal = LifeChecklistItem::query()->active()->count();
        $checklistDone = LifeChecklistCheck::query()
            ->where('worker_id', $worker->id)
            ->whereHas('item', fn ($q) => $q->where('active', true))
            ->count();

        $onboarding = OnboardingSubmission::where('worker_id', $worker->id)
            ->latest('id')
            ->first();

        return response()->json([
            'data' => [
                'worker' => [
                    'name' => $worker->name,
                    'nationality' => $worker->nationality,
                    'status' => $worker->status->value,
                    'status_label' => $worker->status->label(),
                ],

                // 어느 농가에 언제까지 — 근로자가 가장 궁금해하는 정보
                'placement' => $placement === null ? null : [
                    'farm' => $placement->farm?->name,
                    'start_date' => $placement->start_date?->toDateString(),
                    'end_date' => $placement->end_date?->toDateString(),
                ],

                'arrival' => $arrival === null ? null : [
                    'status' => $arrival->status->value,
                    'status_label' => $arrival->status->label(),
                    'step' => $arrival->status->step(),
                    'total_steps' => count(ArrivalStatus::cases()),
                    'scheduled_arrival_at' => $arrival->scheduled_arrival_at?->toIso8601String(),
                ],

                'life_checklist' => [
                    'total' => $checklistTotal,
                    'checked' => $checklistDone,
                    'completed' => $checklistTotal > 0 && $checklistDone >= $checklistTotal,
                ],

                'onboarding' => $onboarding === null ? null : [
                    'status' => $onboarding->status->value,
                    'status_label' => $onboarding->status->label(),
                    'editable' => $onboarding->status->isEditableByWorker(),
                ],

                // 진행 중인 건수 — 배지로 보여 준다
                'counts' => [
                    'open_tickets' => SupportTicket::where('worker_id', $worker->id)
                        ->whereIn('status', [
                            TicketStatus::Open->value,
                            TicketStatus::InProgress->value,
                        ])->count(),

                    'settlements_in_progress' => SettlementRequest::where('worker_id', $worker->id)
                        ->where('status', '!=', SettlementStatus::Done->value)->count(),
                ],
            ],
            'meta' => ['locale' => $worker->locale],
        ]);
    }
}
