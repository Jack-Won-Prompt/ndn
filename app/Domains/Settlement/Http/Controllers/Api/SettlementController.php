<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Http\Controllers\Api;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Actions\RequestSettlementAction;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Enums\SettlementType;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use RuntimeException;

/**
 * 근로자 앱 — 정착 서비스 신청 (CLAUDE.md §9, 업무흐름 §6).
 *
 * 통장·보험·통신·유심 4종. 인증된 Worker 본인 건만 조회·신청한다.
 * 대리점 정보(어느 대리점이 맡았는지)는 근로자에게 내려주지 않는다 — 근로자에게
 * 필요한 것은 진행 상태이고, 대리점 배정은 운영 내부 정보다.
 */
class SettlementController extends Controller
{
    /** 본인 신청 목록 + 신청 가능한 유형 안내 */
    public function index(Request $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $requests = SettlementRequest::where('worker_id', $worker->id)
            ->latest('id')
            ->get();

        // 진행 중인 유형은 다시 신청할 수 없다 — 앱이 버튼을 잠그는 데 쓴다.
        $inProgress = $requests
            ->filter(fn (SettlementRequest $r) => $r->status !== SettlementStatus::Done)
            ->map(fn (SettlementRequest $r) => $r->type->value)
            ->values()
            ->all();

        return response()->json([
            'data' => $requests->map(fn (SettlementRequest $r) => [
                'id' => $r->id,
                'type' => $r->type->value,
                'type_label' => $r->type->label(),
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'created' => $r->created_at?->toIso8601String(),
                'completed_at' => $r->completed_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'types' => array_map(
                    fn (SettlementType $t) => ['value' => $t->value, 'label' => $t->label()],
                    SettlementType::cases(),
                ),
                'in_progress' => $inProgress,
                // 동의가 없으면 신청 자체가 막히므로 앱이 먼저 안내한다(§7-4).
                // 서비스 이용 + 제휴 대리점 제공 동의가 모두 있어야 한다.
                'has_consent' => RequestSettlementAction::hasRequiredConsents($worker),
            ],
        ]);
    }

    /** 신청 */
    public function store(Request $request, RequestSettlementAction $action): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', new Enum(SettlementType::class)],
        ]);

        /** @var Worker $worker */
        $worker = $request->user();

        try {
            $settlement = $action->execute($worker, SettlementType::from($data['type']));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $settlement->id,
                'type' => $settlement->type->value,
                'type_label' => $settlement->type->label(),
                'status' => $settlement->status->value,
                'status_label' => $settlement->status->label(),
            ],
        ], 201);
    }
}
