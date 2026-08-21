<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Actions\CreateDemandRequestAction;
use App\Domains\Demand\Actions\DeleteDemandRequestAction;
use App\Domains\Demand\Actions\SubmitDemandRequestAction;
use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Actions\CancelPlacementAction;
use App\Domains\Matching\Actions\ClosePlacementsAction;
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

    /** 아직 사람을 채워야 하는 수요로 보는 상태들. */
    private const OPEN_STATUSES = [
        DemandStatus::Draft->value,
        DemandStatus::Submitted->value,
        DemandStatus::Aggregated->value,
        DemandStatus::LetterIssued->value,
    ];

    /**
     * 농가 목록 — 기준정보와 **같은 칸**에 이 화면에서 필요한 진행 상황을 덧붙인다.
     *
     * 농가를 여기서 바로 등록할 수 있게 한 이유는, 본사가 농가를 받아 적은 뒤
     * 곧바로 사람을 붙이기 때문이다. 화면을 오가는 사이에 방금 적은 농가를
     * 다시 찾는 일이 없도록 한 자리에서 끝낸다.
     *
     * 저장·엑셀은 기준정보와 같은 엔드포인트를 쓴다 — 검증 규칙이 두 벌로
     * 갈라지면 어느 화면으로 넣었느냐에 따라 다른 데이터가 남는다.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function farmRows(): array
    {
        $demands = DemandRequest::query()
            ->whereIn('status', self::OPEN_STATUSES)
            ->selectRaw('farm_id, count(*) as c, sum(headcount) as need')
            ->groupBy('farm_id')
            ->get()
            ->keyBy('farm_id');

        $placed = Placement::query()
            ->whereIn('status', [PlacementStatus::Proposed->value, PlacementStatus::Confirmed->value])
            ->selectRaw('farm_id, count(*) as c')
            ->groupBy('farm_id')
            ->pluck('c', 'farm_id');

        return collect(BaseInfoGridController::farmRows())
            ->map(function (array $row) use ($demands, $placed) {
                $d = $demands->get($row['id']);

                $row['demands'] = (int) ($d->c ?? 0);
                $row['need'] = (int) ($d->need ?? 0);
                $row['placed'] = (int) ($placed[$row['id']] ?? 0);
                // 편집기가 없는 칸이라 눌러도 셀이 열리지 않는다 → 여는 버튼으로 쓴다.
                $row['assign'] = '인력 배정 ▸';

                return $row;
            })
            ->all();
    }

    /**
     * 농가 1곳 — 이 농가의 수요와 배정 현황.
     *
     * 배정은 수요(인원·기간)에 매달려 있다. 그래서 농가를 고른 다음 어느 수요에
     * 채울지를 한 번 더 고르게 한다. 수요가 하나뿐이면 화면이 알아서 그것을 편다.
     */
    public function farm(Farm $farm): JsonResponse
    {
        $farm->load('city:id,name');

        $demands = DemandRequest::query()
            ->with(['farm:id,name,city_id', 'city:id,name'])
            ->where('farm_id', $farm->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DemandRequest $d) => self::presentDemand($d))
            ->all();

        $placements = Placement::query()
            ->with(['worker:id,name,nationality', 'farm:id,name'])
            ->where('farm_id', $farm->id)
            ->orderByDesc('id')
            ->get();

        self::logAccess($placements->pluck('worker_id')->all(), 'matching-farm');

        return response()->json([
            'farm' => [
                'id' => $farm->id,
                'name' => $farm->name,
                'city' => $farm->city?->name ?? '지자체 미지정',
                'crop' => $farm->main_crop,
                'address' => $farm->address,
            ],
            'demands' => $demands,
            'placements' => $placements->map(fn (Placement $p) => self::presentPlacement($p))->all(),
        ]);
    }

    /**
     * 이 농가의 수요를 그 자리에서 만든다.
     *
     * 농가만 새로 넣고 끝내면 배정 버튼이 아무 데도 닿지 않는다 — 인원과 기간을
     * 모르는 채로는 배정을 만들 수 없기 때문이다. 그래서 수요를 여기서 받는다.
     *
     * 만들자마자 '제출됨'으로 올린다. 본사가 콘솔에서 직접 적어 넣는 수요는 농가가
     * 작성 중인 초안이 아니라 이미 접수된 건이고, 그래야 [수요별 매칭] 목록에도
     * 곧바로 나타난다.
     */
    public function storeDemand(
        Request $request,
        Farm $farm,
        CreateDemandRequestAction $create,
        SubmitDemandRequestAction $submit,
    ): JsonResponse {
        $data = $request->validate([
            'nationality' => ['required', 'string', 'size:2'],
            'headcount' => ['required', 'integer', 'min:1', 'max:999'],
            'gender' => ['required', 'in:male,female,any'],
            'crop' => ['required', 'string', 'max:100'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'age_min' => ['nullable', 'integer', 'min:18', 'max:99'],
            'age_max' => ['nullable', 'integer', 'min:18', 'max:99', 'gte:age_min'],
            'allow_siblings' => ['nullable', 'boolean'],
        ], [
            'period_end.after' => '종료일은 시작일보다 뒤여야 합니다.',
            'age_max.gte' => '최대 나이는 최소 나이보다 커야 합니다.',
        ]);

        $demand = $create->execute($farm, $data);
        $submit->execute($demand);

        return response()->json(['ok' => true, 'demand_id' => $demand->id]);
    }

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

    /**
     * 배정 현황 표에서 체크한 건을 한 번에 확정·취소한다.
     *
     * 표에는 셀 안에 버튼을 둘 수 없어(편집기 없는 칸은 글자만 그린다) 체크 →
     * 툴바 순서로 처리한다. 스무 건을 스무 번 누르지 않아도 되는 편이 낫기도 하다.
     *
     * 한 건이 막혀도 나머지는 진행한다. 이미 확정된 건이 섞였다고 스무 건이 통째로
     * 되돌아가면 무엇이 걸렸는지 찾기만 어려워진다 — 대신 몇 건이 왜 걸렸는지 돌려준다.
     */
    public function bulk(
        Request $request,
        ConfirmPlacementAction $confirm,
        CancelPlacementAction $cancel,
    ): JsonResponse {
        $data = $request->validate([
            'action' => ['required', 'in:confirm,cancel,delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:placements,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $picked = Placement::whereIn('id', $data['ids'])->get();

        if ($data['action'] === 'delete') {
            // 지우기 전에 취소를 거친다 — 그래야 근로자가 풀리고 농가 자리가 빈다.
            // 그 순서는 ClosePlacementsAction 한 곳에서만 정한다.
            $swept = app(ClosePlacementsAction::class)->execute(
                $picked,
                Auth::user(),
                filled($data['reason'] ?? null) ? $data['reason'] : '배정 삭제',
            );

            return response()->json([
                'ok' => true,
                'message' => "배정 {$swept['placements']}건을 삭제했습니다."
                    .($swept['cancelled'] > 0 ? " (진행 중이던 {$swept['cancelled']}건은 취소 처리해 근로자를 미배정으로 돌렸습니다)" : '')
                    .($swept['arrivals'] > 0 ? " · 입국 기록 {$swept['arrivals']}건 정리" : ''),
                'rows' => self::placementRows(),
                'demand_rows' => self::rows(),
            ]);
        }

        $done = 0;
        $failed = [];

        foreach ($picked as $placement) {
            try {
                $data['action'] === 'confirm'
                    ? $confirm->execute($placement, Auth::user())
                    : $cancel->execute($placement, Auth::user(), $data['reason'] ?? null);
                $done++;
            } catch (RuntimeException $e) {
                $failed[] = '#'.$placement->id.' '.$e->getMessage();
            }
        }

        $word = $data['action'] === 'confirm' ? '확정' : '취소';

        return response()->json([
            'ok' => true,
            'message' => $failed === []
                ? "{$done}건을 {$word}했습니다."
                : "{$done}건 {$word} · ".count($failed).'건 건너뜀 — '.implode(' / ', array_slice($failed, 0, 3)),
            'rows' => self::placementRows(),
            // 확정·취소는 농가 정원을 움직인다. 수요 표의 진행률도 함께 새로 준다.
            'demand_rows' => self::rows(),
        ]);
    }

    /**
     * 수요 표에서 체크한 건을 지운다.
     *
     * 배정은 함께 지우지 않는다 — 배정은 농가에 매여 있지 수요에 매여 있지 않다.
     * 잘못 적은 신청서를 지웠다고 이미 그 농가에서 일하는 사람이 사라지면 안 된다.
     */
    public function deleteDemands(Request $request, DeleteDemandRequestAction $action): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:demand_requests,id'],
        ]);

        $swept = $action->execute(array_map('intval', $data['ids']), Auth::user());

        return response()->json([
            'ok' => true,
            'message' => DeleteDemandRequestAction::describe($swept),
            'rows' => self::rows(),
            // 농가 표의 '수요' 숫자도 함께 어긋난다.
            'farm_rows' => self::farmRows(),
        ]);
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
            // 편집기가 없는 칸이라 눌러도 셀이 열리지 않는다 → 여는 버튼으로 쓴다.
            'pick' => '인력 배정 ▸',
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
            // 표에는 참/거짓이 아니라 읽을 글자가 필요하다 (엑셀로도 그대로 나간다).
            'group_label' => $placement->placement_group_id !== null ? '그룹' : '',
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
