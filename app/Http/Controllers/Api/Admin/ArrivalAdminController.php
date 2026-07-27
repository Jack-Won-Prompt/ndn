<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domains\Arrival\Actions\AdvanceArrivalStageAction;
use App\Domains\Arrival\Actions\UpdateArrivalDetailsAction;
use App\Domains\Arrival\Enums\ArrivalDocument;
use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Matching\Models\Placement;
use App\Http\Controllers\Api\Admin\Concerns\ScopesPortalQueries;
use App\Http\Controllers\Controller;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 관리자 앱 — 입국한 근로자 관리 (업무흐름 §5).
 *
 * 배정 확정된 근로자의 입국 예정 → 공항 도착 → 픽업 → 농가 인계까지를 관리한다.
 * 역할별 스코프는 배정된 농가 기준이다(PortalScope::placements).
 *
 * 위치는 다루지 않는다(§7-2). 진행 증빙은 확인 시각과 담당자로만 남긴다.
 */
class ArrivalAdminController extends Controller
{
    use ScopesPortalQueries;

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $query = ArrivalRecord::query()
            ->whereHas('placement', fn ($p) => PortalScope::placements($p, $actor))
            ->with([
                'placement.worker:id,name,nationality',
                'placement.farm:id,name',
                'pickupUser:id,name',
            ])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value()),
            )
            // 아직 인계되지 않은 건을 앞으로, 도착 예정이 임박한 순
            ->orderByRaw("CASE WHEN status = 'handed_over' THEN 1 ELSE 0 END")
            ->orderByRaw('scheduled_arrival_at IS NULL')
            ->orderBy('scheduled_arrival_at');

        $page = $query->paginate($this->perPage($request));

        $this->logWorkerAccess(
            $actor,
            collect($page->items())->pluck('placement.worker_id')->filter()->all(),
            'arrival-list',
        );

        return response()->json([
            'data' => collect($page->items())->map(fn (ArrivalRecord $r) => $this->present($r))->all(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'statuses' => array_map(
                    fn (ArrivalStatus $s) => ['value' => $s->value, 'label' => $s->label(), 'step' => $s->step()],
                    ArrivalStatus::cases(),
                ),
                'documents' => array_map(
                    fn (ArrivalDocument $d) => [
                        'key' => $d->value,
                        'label' => $d->label(),
                        'required' => $d->isRequired(),
                    ],
                    ArrivalDocument::cases(),
                ),
                'can_decide' => PortalScope::canDecide($actor),
            ],
        ]);
    }

    /** 항공편·픽업 배차·서류 체크리스트 갱신 */
    public function update(Request $request, int $arrival, UpdateArrivalDetailsAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);
        $record = $this->findInScope($actor, $arrival);

        $data = $request->validate([
            'flight_no' => ['nullable', 'string', 'max:20'],
            'airport' => ['nullable', 'string', 'max:60'],
            'scheduled_arrival_at' => ['nullable', 'date'],
            'pickup_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'vehicle_no' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:1000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['boolean'],
        ]);

        $action->execute($record, $data, $data['documents'] ?? null, $actor);

        return response()->json(['data' => $this->present($this->reload($record))]);
    }

    /** 다음 단계로 진행 (도착 확인 → 픽업 완료 → 농가 인계) */
    public function advance(Request $request, int $arrival, AdvanceArrivalStageAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);
        $record = $this->findInScope($actor, $arrival);

        $data = $request->validate([
            'status' => ['required', 'in:arrived,picked_up,handed_over'],
        ]);

        try {
            $action->execute($record, ArrivalStatus::from($data['status']), $actor);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($this->reload($record))]);
    }

    private function findInScope($actor, int $arrival): ArrivalRecord
    {
        $record = ArrivalRecord::query()
            ->whereKey($arrival)
            ->whereHas('placement', fn ($p) => PortalScope::placements($p, $actor))
            ->first();

        abort_if($record === null, 404, '해당 입국 건을 찾을 수 없습니다.');

        return $record;
    }

    private function reload(ArrivalRecord $record): ArrivalRecord
    {
        return $record->refresh()->load([
            'placement.worker:id,name,nationality',
            'placement.farm:id,name',
            'pickupUser:id,name',
        ]);
    }

    private function present(ArrivalRecord $record): array
    {
        /** @var Placement|null $placement */
        $placement = $record->placement;

        return [
            'id' => $record->id,
            'status' => $record->status->value,
            'status_label' => $record->status->label(),
            'step' => $record->status->step(),
            'next_status' => $record->status->next()?->value,
            'next_label' => $record->status->next()?->label(),

            'worker_id' => $placement?->worker_id,
            'worker_name' => $placement?->worker?->name,
            'nationality' => $placement?->worker?->nationality,
            'farm' => $placement?->farm?->name,

            'flight_no' => $record->flight_no,
            'airport' => $record->airport,
            'scheduled_arrival_at' => $record->scheduled_arrival_at?->toIso8601String(),
            'pickup_user' => $record->pickupUser?->name,
            'pickup_user_id' => $record->pickup_user_id,
            'vehicle_no' => $record->vehicle_no,

            'arrived_at' => $record->arrived_at?->toIso8601String(),
            'picked_up_at' => $record->picked_up_at?->toIso8601String(),
            'handed_over_at' => $record->handed_over_at?->toIso8601String(),

            'documents' => $record->checklist(),
            'missing_required' => $record->missingRequiredDocuments(),
            'note' => $record->note,
        ];
    }
}
