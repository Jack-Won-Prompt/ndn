<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domains\Monitoring\Actions\RecordWorkReviewAction;
use App\Domains\Monitoring\Enums\WorkReviewResult;
use App\Domains\Monitoring\Enums\WorkReviewSection;
use App\Domains\Monitoring\Enums\WorkReviewType;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Monitoring\Models\WorkReviewItem;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Api\Admin\Concerns\ScopesPortalQueries;
use App\Http\Controllers\Controller;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * 관리자 앱 — 근무상태 종합 점검표 (업무흐름 §7).
 *
 * 점검자가 현장에서 근로자 한 사람을 점검하며 작성한다. 항목 목록은 서버가
 * 내려주므로 항목이 바뀌어도 앱을 다시 배포하지 않는다.
 *
 * 위치는 저장하지 않는다 — 점검자 좌표는 체크인(inspection_checkins)에만 남는다(§7-2).
 */
class WorkReviewAdminController extends Controller
{
    use ScopesPortalQueries;

    /** 점검 화면을 그릴 항목표 — 영역·보기까지. */
    public function form(Request $request): JsonResponse
    {
        $this->actor($request);

        $items = WorkReviewItem::query()->active()->get()
            ->groupBy(fn (WorkReviewItem $i) => $i->section->value);

        return response()->json([
            'data' => collect(WorkReviewSection::ordered())->map(fn (WorkReviewSection $s) => [
                'key' => $s->value,
                'label' => $s->label(),
                'options' => collect($s->options())->map(fn ($label, $value) => [
                    'value' => $value, 'label' => $label,
                ])->values()->all(),
                'items' => ($items->get($s->value) ?? collect())
                    ->map(fn (WorkReviewItem $i) => ['id' => $i->id, 'label' => $i->label])
                    ->values()->all(),
            ])->all(),
            'meta' => [
                'types' => collect(WorkReviewType::cases())
                    ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all(),
                'results' => collect(WorkReviewResult::cases())
                    ->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])->all(),
            ],
        ]);
    }

    /** 점검 이력 — 역할 스코프 안의 근로자만. */
    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $query = PortalScope::byWorker(WorkReview::query(), $actor)
            ->with(['worker:id,name,nationality', 'farm:id,name', 'inspector:id,name'])
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id');

        $page = $query->paginate($this->perPage($request));

        $this->logWorkerAccess(
            $actor,
            collect($page->items())->pluck('worker_id')->all(),
            'work-reviews.index',
        );

        return response()->json([
            'data' => collect($page->items())->map(fn (WorkReview $r) => $this->present($r))->all(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'can_decide' => PortalScope::canDecide($actor),
            ],
        ]);
    }

    public function store(Request $request, RecordWorkReviewAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);

        $data = $request->validate([
            'worker_id' => ['required', 'integer'],
            'farm_visit_id' => ['nullable', 'integer', 'exists:farm_visits,id'],
            'reviewed_at' => ['required', 'date'],
            'place' => ['nullable', 'string', 'max:200'],
            'review_type' => ['required', Rule::enum(WorkReviewType::class)],

            'overtime_done' => ['nullable', 'boolean'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'overtime_consented' => ['nullable', 'boolean'],

            'avg_monthly_wage' => ['nullable', 'string', 'max:50'],
            'last_paid_on' => ['nullable', 'date'],
            'wage_unpaid' => ['nullable', 'boolean'],
            'board_provided' => ['nullable', 'boolean'],
            'contract_followed' => ['nullable', 'boolean'],
            'contract_violation' => ['nullable', 'string', 'max:2000'],

            'result' => ['required', Rule::enum(WorkReviewResult::class)],
            'notable' => ['nullable', 'string', 'max:2000'],
            'improvements' => ['nullable', 'string', 'max:2000'],
            'farm_requests' => ['nullable', 'string', 'max:2000'],

            'action_due_on' => ['nullable', 'date'],
            'action_assignee' => ['nullable', 'string', 'max:100'],
            'recheck_on' => ['nullable', 'date'],
            'report_city' => ['nullable', 'boolean'],
            'report_immigration' => ['nullable', 'boolean'],
            'action_note' => ['nullable', 'string', 'max:2000'],

            'signed_inspector' => ['nullable', 'string', 'max:100'],
            'signed_farm' => ['nullable', 'string', 'max:100'],
            'signed_worker' => ['nullable', 'string', 'max:100'],
            'signed_interpreter' => ['nullable', 'string', 'max:100'],

            'answers' => ['nullable', 'array'],
        ]);

        // 스코프 밖 근로자를 점검 대상으로 넣을 수 없다.
        abort_unless(PortalScope::canSeeWorker($actor, (int) $data['worker_id']), 404, '해당 근로자를 찾을 수 없습니다.');

        $worker = Worker::findOrFail($data['worker_id']);

        try {
            $review = $action->execute($worker, $actor, $data, (array) $request->input('answers', []));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->present($review->load(['worker:id,name,nationality', 'farm:id,name', 'inspector:id,name'])),
        ], 201);
    }

    /** @return array<string, mixed> */
    private function present(WorkReview $r): array
    {
        return [
            'id' => $r->id,
            'worker' => ['id' => $r->worker_id, 'name' => $r->worker?->name, 'nationality' => $r->worker?->nationality],
            'farm' => ['id' => $r->farm_id, 'name' => $r->farm?->name],
            'inspector' => $r->inspector?->name,
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'review_type' => $r->review_type->value,
            'review_type_label' => $r->review_type->label(),
            'result' => $r->result->value,
            'result_label' => $r->result->label(),
            'risk_level' => $r->risk_level->value,
            'risk_label' => $r->risk_level->label(),
            'risk_score' => $r->risk_score,
            'recheck_on' => $r->recheck_on?->toDateString(),
        ];
    }
}
