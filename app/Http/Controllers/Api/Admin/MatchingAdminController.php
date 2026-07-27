<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Matching\Actions\CancelPlacementAction;
use App\Domains\Matching\Actions\ConfirmPlacementAction;
use App\Domains\Matching\Actions\CreatePlacementAction;
use App\Domains\Matching\Actions\MatchCandidatesAction;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Http\Controllers\Api\Admin\Concerns\ScopesPortalQueries;
use App\Http\Controllers\Controller;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 관리자 앱 — 매칭 (업무흐름 §4).
 *
 * 흐름: 수요 목록 → 수요별 추천 후보 → 배정(제안) → 확정 → 입국 단계로 인계.
 * 확정하면 ConfirmPlacementAction 이 입국 기록까지 만든다.
 */
class MatchingAdminController extends Controller
{
    use ScopesPortalQueries;

    /** 매칭 대상 수요 목록 — 배정 진행률 포함 */
    public function demands(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $query = DemandRequest::query()
            ->whereHas('farm', fn ($f) => PortalScope::farms($f, $actor))
            ->with(['farm:id,name,city_id', 'city:id,name'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value()),
                // 기본은 매칭을 진행할 수 있는 상태만
                fn ($q) => $q->whereIn('status', [
                    DemandStatus::Submitted->value,
                    DemandStatus::Aggregated->value,
                    DemandStatus::LetterIssued->value,
                ]),
            )
            ->orderBy('period_start');

        $page = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => collect($page->items())
                ->map(fn (DemandRequest $d) => $this->presentDemand($d))->all(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'statuses' => array_map(
                    fn (DemandStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    DemandStatus::cases(),
                ),
                'can_decide' => PortalScope::canDecide($actor),
            ],
        ]);
    }

    /** 수요 조건에 맞는 추천 후보 */
    public function candidates(Request $request, int $demand, MatchCandidatesAction $action): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->findDemand($actor, $demand);

        $candidates = $action->execute($model);

        $this->logWorkerAccess(
            $actor,
            $candidates->map(fn (array $c) => $c['worker']->id)->all(),
            'matching-candidates',
        );

        return response()->json([
            'data' => $candidates->map(fn (array $c) => [
                'id' => $c['worker']->id,
                'name' => $c['worker']->name,
                'nationality' => $c['worker']->nationality,
                'gender' => $c['worker']->gender?->value,
                'age' => $c['worker']->age(),
                'score' => $c['score'],
                // 항목별 대조 결과 — null 은 정보가 없어 판단 불가
                'matches' => $c['matches'],
            ])->all(),
            'meta' => [
                'demand' => $this->presentDemand($model),
                'can_decide' => PortalScope::canDecide($actor),
            ],
        ]);
    }

    /** 배정 목록 */
    public function placements(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $query = PortalScope::placements(Placement::query(), $actor)
            ->with(['worker:id,name,nationality', 'farm:id,name'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value()),
            )
            ->orderByDesc('id');

        $page = $query->paginate($this->perPage($request));

        $this->logWorkerAccess($actor, $page->pluck('worker_id')->all(), 'placement-list');

        return response()->json([
            'data' => collect($page->items())
                ->map(fn (Placement $p) => $this->presentPlacement($p))->all(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'statuses' => array_map(
                    fn (PlacementStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    PlacementStatus::cases(),
                ),
                'can_decide' => PortalScope::canDecide($actor),
            ],
        ]);
    }

    /** 배정 생성 (여러 명 동시, 형제·가족은 그룹으로) */
    public function store(Request $request, CreatePlacementAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);

        $data = $request->validate([
            'demand_id' => ['required', 'integer'],
            'worker_ids' => ['required', 'array', 'min:1'],
            'worker_ids.*' => ['integer', 'exists:workers,id'],
            'as_group' => ['nullable', 'boolean'],
        ]);

        $demand = $this->findDemand($actor, $data['demand_id']);

        try {
            $created = $action->execute(
                $demand,
                $data['worker_ids'],
                $actor,
                $data['as_group'] ?? false,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $created->map(
                fn (Placement $p) => $this->presentPlacement($p->load(['worker:id,name,nationality', 'farm:id,name']))
            )->all(),
        ], 201);
    }

    /** 배정 확정 — 입국 기록이 함께 생성된다 */
    public function confirm(Request $request, int $placement, ConfirmPlacementAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);
        $model = $this->findPlacement($actor, $placement);

        try {
            $action->execute($model, $actor);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->presentPlacement($model->refresh()->load(['worker:id,name,nationality', 'farm:id,name'])),
        ]);
    }

    /** 배정 취소 (사유 기록) */
    public function cancel(Request $request, int $placement, CancelPlacementAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);
        $model = $this->findPlacement($actor, $placement);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $action->execute($model, $actor, $data['reason'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->presentPlacement($model->refresh()->load(['worker:id,name,nationality', 'farm:id,name'])),
        ]);
    }

    private function findDemand($actor, int $id): DemandRequest
    {
        $model = DemandRequest::query()
            ->whereKey($id)
            ->whereHas('farm', fn ($f) => PortalScope::farms($f, $actor))
            ->with(['farm:id,name,city_id', 'city:id,name'])
            ->first();

        abort_if($model === null, 404, '해당 수요를 찾을 수 없습니다.');

        return $model;
    }

    private function findPlacement($actor, int $id): Placement
    {
        $model = PortalScope::placements(Placement::query()->whereKey($id), $actor)->first();

        abort_if($model === null, 404, '해당 배정을 찾을 수 없습니다.');

        return $model;
    }

    private function presentDemand(DemandRequest $demand): array
    {
        // 이 농가에 이미 잡힌(제안·확정) 인원
        $filled = Placement::where('farm_id', $demand->farm_id)
            ->whereIn('status', [
                PlacementStatus::Proposed->value,
                PlacementStatus::Confirmed->value,
            ])
            ->count();

        return [
            'id' => $demand->id,
            'farm' => $demand->farm?->name,
            'farm_id' => $demand->farm_id,
            'city' => $demand->city?->name,
            'nationality' => $demand->nationality,
            'headcount' => $demand->headcount,
            'filled' => $filled,
            'remaining' => max($demand->headcount - $filled, 0),
            'age_min' => $demand->age_min,
            'age_max' => $demand->age_max,
            'gender' => $demand->gender?->value,
            'gender_label' => $demand->gender?->label(),
            'allow_siblings' => (bool) $demand->allow_siblings,
            'crop' => $demand->crop,
            'period_start' => $demand->period_start?->toDateString(),
            'period_end' => $demand->period_end?->toDateString(),
            'status' => $demand->status->value,
            'status_label' => $demand->status->label(),
        ];
    }

    private function presentPlacement(Placement $placement): array
    {
        return [
            'id' => $placement->id,
            'worker_id' => $placement->worker_id,
            'worker_name' => $placement->worker?->name,
            'nationality' => $placement->worker?->nationality,
            'farm' => $placement->farm?->name,
            'status' => $placement->status->value,
            'status_label' => $placement->status->label(),
            // 같은 값이면 형제·가족으로 함께 배치된 건
            'group_id' => $placement->placement_group_id,
            'start_date' => $placement->start_date?->toDateString(),
            'end_date' => $placement->end_date?->toDateString(),
            'note' => $placement->note,
        ];
    }
}
