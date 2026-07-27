<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domains\Recruitment\Actions\ApproveWorkerAction;
use App\Domains\Recruitment\Actions\RejectWorkerAction;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Api\Admin\Concerns\ScopesPortalQueries;
use App\Http\Controllers\Controller;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 관리자 앱 — 근로자 목록·승인·상태 관리 (업무흐름 §2·§9).
 *
 * 역할별로 보이는 근로자가 다르다(PortalScope). 상태 변경은 NDN 관리자만 한다.
 * 목록·상세 조회는 개인정보 열람이므로 감사 로그를 남긴다(§7-6).
 */
class WorkerAdminController extends Controller
{
    use ScopesPortalQueries;

    /** 근로자 목록 (필터: status, nationality, q) */
    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $query = PortalScope::workers(Worker::query(), $actor)
            ->with(['placements.farm:id,name,city_id'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value()),
            )
            ->when(
                $request->filled('nationality'),
                fn ($q) => $q->where('nationality', $request->string('nationality')->value()),
            )
            // 이름 검색만 지원한다. 여권번호는 암호문이라 LIKE 가 불가능하고,
            // blind index 는 완전일치만 되므로 별도 처리한다(§7-1).
            ->when(
                $request->filled('q'),
                fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->value().'%'),
            )
            ->orderByDesc('id');

        $page = $query->paginate($this->perPage($request));

        $this->logWorkerAccess($actor, $page->pluck('id')->all(), 'worker-list');

        return response()->json([
            'data' => collect($page->items())->map(fn (Worker $w) => $this->summary($w))->all(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'statuses' => $this->statusOptions(),
                'can_decide' => PortalScope::canDecide($actor),
            ],
        ]);
    }

    /** 근로자 상세 */
    public function show(Request $request, int $worker): JsonResponse
    {
        $actor = $this->actor($request);

        $model = PortalScope::workers(Worker::query()->whereKey($worker), $actor)
            ->with(['placements.farm:id,name,city_id'])
            ->first();

        abort_if($model === null, 404, '해당 근로자를 찾을 수 없습니다.');

        $this->logWorkerAccess($actor, [$model->id], 'worker-detail');

        return response()->json([
            'data' => $this->summary($model) + [
                // 상세에서만 추가로 보여주는 항목. 여권번호는 마스킹해 내려준다(§7-1).
                'passport_masked' => $this->maskPassport($model->passport_no),
                'approved_at' => $model->approved_at?->toIso8601String(),
            ],
        ]);
    }

    /** 가입 승인 (pending → active) */
    public function approve(Request $request, int $worker, ApproveWorkerAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);
        $model = $this->findInScope($actor, $worker);

        try {
            $action->execute($model, $actor);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->summary($model->refresh())]);
    }

    /** 가입 거절 (pending → rejected) */
    public function reject(Request $request, int $worker, RejectWorkerAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);
        $model = $this->findInScope($actor, $worker);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $action->execute($model, $actor, $data['reason'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->summary($model->refresh())]);
    }

    /** 재직 상태 변경 (재직 ↔ 비활성 ↔ 귀국) — 입국한 근로자 관리에 쓴다 */
    public function updateStatus(Request $request, int $worker): JsonResponse
    {
        $actor = $this->authorizeDecision($request);
        $model = $this->findInScope($actor, $worker);

        $data = $request->validate([
            'status' => ['required', 'in:active,inactive,returned'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $target = WorkerStatus::from($data['status']);

        // 승인 흐름(pending/rejected)은 approve·reject 로만 다룬다.
        if (in_array($model->status, [WorkerStatus::Pending, WorkerStatus::Rejected], true)) {
            return response()->json([
                'message' => '승인 대기·거절 상태는 승인/거절로만 처리할 수 있습니다.',
            ], 422);
        }

        $from = $model->status;
        $model->status = $target;
        $model->save();

        activity('worker-account')
            ->performedOn($model)
            ->causedBy($actor)
            ->withProperties([
                'from' => $from->value,
                'to' => $target->value,
                'reason' => $data['reason'] ?? null,
            ])
            ->log('근로자 상태 변경');

        return response()->json(['data' => $this->summary($model->refresh())]);
    }

    private function findInScope($actor, int $worker): Worker
    {
        $model = PortalScope::workers(Worker::query()->whereKey($worker), $actor)->first();

        abort_if($model === null, 404, '해당 근로자를 찾을 수 없습니다.');

        return $model;
    }

    /**
     * 목록·상세 공통 표현. 여권번호·생년월일 등 민감 원문은 넣지 않는다(§7-1).
     */
    private function summary(Worker $worker): array
    {
        $placement = $worker->currentPlacement();

        return [
            'id' => $worker->id,
            'name' => $worker->name,
            'nationality' => $worker->nationality,
            'locale' => $worker->locale,
            'status' => $worker->status->value,
            'status_label' => $worker->status->label(),
            'farm' => $placement?->farm?->name,
            'farm_id' => $placement?->farm_id,
        ];
    }

    /** 여권번호는 앞 1자리만 노출한다. */
    private function maskPassport(?string $passport): ?string
    {
        if ($passport === null || $passport === '') {
            return null;
        }

        return mb_substr($passport, 0, 1).str_repeat('•', max(mb_strlen($passport) - 1, 1));
    }

    /** @return list<array{value:string,label:string}> */
    private function statusOptions(): array
    {
        return array_map(
            fn (WorkerStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            WorkerStatus::cases(),
        );
    }
}
