<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Matching\Actions\CancelPlacementAction;
use App\Domains\Matching\Actions\ConfirmPlacementAction;
use App\Domains\Matching\Actions\CreatePlacementAction;
use App\Domains\Matching\Actions\MatchCandidatesAction;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * 콘솔 — 농가↔근로자 매칭 (업무흐름 §4).
 *
 * 지금까지 배정을 만들 수 있는 곳은 관리자 앱(API) 뿐이었다. 본사가 농가를
 * 등록하고 인력을 직접 가입시키는 흐름에서 마지막 한 칸이 콘솔에 없었다.
 *
 * 판단은 전부 기존 Action 이 한다(§4). 이 컨트롤러는 화면에 필요한 모양으로
 * 골라 담고, 실패 사유를 그대로 화면에 돌려주는 일만 한다.
 */
class MatchingController extends Controller
{
    /** 후보를 한 번에 너무 많이 복호화하지 않는다(나이 계산에 birth_date 가 필요하다). */
    private const MAX_POOL = 200;

    /**
     * 매칭을 진행할 수 있는 수요 목록 — 농가별 배정 진행률 포함.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(): array
    {
        return DemandRequest::query()
            ->with(['farm:id,name,city_id', 'city:id,name'])
            ->whereIn('status', [
                DemandStatus::Submitted->value,
                DemandStatus::Aggregated->value,
                DemandStatus::LetterIssued->value,
            ])
            ->orderBy('period_start')
            ->get()
            ->map(fn (DemandRequest $d) => self::presentDemand($d))
            ->all();
    }

    /**
     * 배정 현황 — 제안·확정·취소 전부. 근로자 이름이 보이므로 열람 기록을 남긴다(§7-6).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function placementRows(): array
    {
        $placements = Placement::query()
            ->with(['worker:id,name,nationality', 'farm:id,name'])
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        self::logAccess($placements->pluck('worker_id')->all(), 'matching-placements');

        return $placements->map(fn (Placement $p) => self::presentPlacement($p))->all();
    }

    /**
     * 수요 1건 — 조건에 맞는 추천 후보 + 이 농가에 이미 잡힌 배정.
     *
     * 추천에 걸리지 않는 사람도 배정해야 할 때가 있어(국적 표기가 다르거나
     * 현장에서 이미 정해진 경우) 미배정 인력 전체도 함께 내려준다.
     */
    public function show(DemandRequest $demand, MatchCandidatesAction $action): JsonResponse
    {
        $demand->load(['farm:id,name,city_id', 'city:id,name']);

        $candidates = $action->execute($demand);

        $recommendedIds = $candidates->map(fn (array $c) => $c['worker']->id)->all();

        // 추천에서 빠진 미배정·재직 인력 — 조건 밖이라는 표시와 함께 보여 준다.
        $others = Worker::query()
            ->unassigned()
            ->where('status', WorkerStatus::Active->value)
            ->when($recommendedIds !== [], fn ($q) => $q->whereNotIn('id', $recommendedIds))
            ->orderBy('name')
            ->limit(self::MAX_POOL)
            ->get();

        $placements = Placement::query()
            ->with(['worker:id,name,nationality', 'farm:id,name'])
            ->where('farm_id', $demand->farm_id)
            ->orderByDesc('id')
            ->get();

        self::logAccess(
            array_merge($recommendedIds, $others->pluck('id')->all()),
            'matching-candidates',
        );

        return response()->json([
            'demand' => self::presentDemand($demand),
            'candidates' => $candidates->map(fn (array $c) => [
                'id' => $c['worker']->id,
                'name' => $c['worker']->name,
                'nationality' => $c['worker']->nationality,
                'gender' => $c['worker']->gender?->label(),
                'age' => $c['worker']->age(),
                'score' => $c['score'],
                // null 은 '정보가 없어 판단 불가' — 맞지 않음과 구분해서 보여 준다.
                'matches' => $c['matches'],
                'recommended' => true,
            ])->all(),
            'others' => $others->map(fn (Worker $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'nationality' => $w->nationality,
                'gender' => $w->gender?->label(),
                'age' => $w->age(),
                'score' => 0,
                'matches' => [],
                'recommended' => false,
            ])->all(),
            'placements' => $placements->map(fn (Placement $p) => self::presentPlacement($p))->all(),
        ]);
    }

    /** 배정 생성 (여러 명 동시, 형제·가족 동반은 한 그룹으로). */
    public function store(Request $request, CreatePlacementAction $action): JsonResponse
    {
        $data = $request->validate([
            'demand_id' => ['required', 'integer', 'exists:demand_requests,id'],
            'worker_ids' => ['required', 'array', 'min:1'],
            'worker_ids.*' => ['integer', 'exists:workers,id'],
            'as_group' => ['nullable', 'boolean'],
        ]);

        $demand = DemandRequest::findOrFail($data['demand_id']);

        try {
            $created = $action->execute(
                $demand,
                array_map('intval', $data['worker_ids']),
                Auth::user(),
                (bool) ($data['as_group'] ?? false),
            );
        } catch (RuntimeException $e) {
            // 정원 초과·중복 배정은 담당자가 고칠 수 있는 문제라 사유를 그대로 보여 준다.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'count' => $created->count()]);
    }

    /** 배정 확정 — 입국 기록이 함께 만들어진다. */
    public function confirm(Placement $placement, ConfirmPlacementAction $action): JsonResponse
    {
        try {
            $action->execute($placement, Auth::user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /** 배정 취소 (사유 기록). */
    public function cancel(Request $request, Placement $placement, CancelPlacementAction $action): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $action->execute($placement, Auth::user(), $data['reason'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * 수요 한 줄 — 이 농가에 이미 잡힌(제안·확정) 인원까지 함께 센다.
     *
     * @return array<string, mixed>
     */
    private static function presentDemand(DemandRequest $demand): array
    {
        $filled = Placement::where('farm_id', $demand->farm_id)
            ->whereIn('status', [
                PlacementStatus::Proposed->value,
                PlacementStatus::Confirmed->value,
            ])
            ->count();

        $age = match (true) {
            $demand->age_min !== null && $demand->age_max !== null => $demand->age_min.'~'.$demand->age_max.'세',
            $demand->age_min !== null => $demand->age_min.'세 이상',
            $demand->age_max !== null => $demand->age_max.'세 이하',
            default => '무관',
        };

        return [
            'id' => $demand->id,
            'farm' => $demand->farm?->name ?? '—',
            'farm_id' => $demand->farm_id,
            'city' => $demand->city?->name ?? '—',
            'nationality' => $demand->nationality,
            'crop' => $demand->crop,
            'headcount' => $demand->headcount,
            'filled' => $filled,
            'remaining' => max($demand->headcount - $filled, 0),
            'age' => $age,
            'gender' => $demand->gender?->label() ?? '무관',
            'allow_siblings' => (bool) $demand->allow_siblings,
            'period' => trim(($demand->period_start?->toDateString() ?? '').' ~ '.($demand->period_end?->toDateString() ?? '')),
            'status' => $demand->status->value,
            'status_label' => $demand->status->label(),
        ];
    }

    /** @return array<string, mixed> */
    private static function presentPlacement(Placement $placement): array
    {
        return [
            'id' => $placement->id,
            'worker_id' => $placement->worker_id,
            'worker' => $placement->worker?->name ?? '—',
            'nationality' => $placement->worker?->nationality ?? '—',
            'farm' => $placement->farm?->name ?? '—',
            'status' => $placement->status->value,
            'status_label' => $placement->status->label(),
            // 같은 값이면 형제·가족으로 함께 움직이는 건
            'group' => $placement->placement_group_id !== null,
            'start_date' => $placement->start_date?->toDateString() ?? '—',
            'end_date' => $placement->end_date?->toDateString() ?? '—',
            'note' => $placement->note,
            'can_confirm' => $placement->status->canTransitionTo(PlacementStatus::Confirmed),
            'can_cancel' => $placement->status->canTransitionTo(PlacementStatus::Cancelled),
        ];
    }

    /**
     * 근로자 이름을 화면에 띄웠다는 기록(§7-6). 건별로 남기면 로그가 터지므로 묶어서 한 줄.
     *
     * @param  array<int, int|null>  $workerIds
     */
    private static function logAccess(array $workerIds, string $reason): void
    {
        $ids = array_values(array_unique(array_filter($workerIds)));

        if ($ids === [] || Auth::user() === null) {
            return;
        }

        activity('personal-data-access')
            ->causedBy(Auth::user())
            ->withProperties(['reason' => $reason, 'worker_ids' => $ids, 'count' => count($ids)])
            ->log("개인정보 열람: {$reason}");
    }
}
