<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domains\Onboarding\Actions\ReviewOnboardingAction;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Settlement\Actions\MoveSettlementStageAction;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Support\Actions\UpdateTicketStatusAction;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Api\Admin\Concerns\ScopesPortalQueries;
use App\Http\Controllers\Controller;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 관리자 앱 — 처리 업무 3종 (온보딩 검수 · 민원 · 정착 서비스).
 *
 * 세 영역 모두 "본인 근로자 목록 + 상태 전이"라는 같은 모양이라 한 컨트롤러에 모았다.
 * 각 상태 전이는 도메인의 기존 Action 을 그대로 호출한다(§4).
 */
class CaseworkAdminController extends Controller
{
    use ScopesPortalQueries;

    // ── 온보딩 검수 ──────────────────────────────────────────────────────

    public function onboardingIndex(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $query = PortalScope::byWorker(OnboardingSubmission::query(), $actor)
            ->with('worker:id,name,nationality')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value()),
                // 기본은 검수가 필요한 것만 — 관리자가 할 일이 바로 보이게
                fn ($q) => $q->whereIn('status', [
                    OnboardingStatus::Submitted->value,
                    OnboardingStatus::UnderReview->value,
                ]),
            )
            ->orderBy('submitted_at');

        $page = $query->paginate($this->perPage($request));
        $this->logWorkerAccess($actor, $page->pluck('worker_id')->all(), 'onboarding-list');

        return response()->json([
            'data' => collect($page->items())->map(fn (OnboardingSubmission $s) => [
                'id' => $s->id,
                'worker_id' => $s->worker_id,
                'worker_name' => $s->worker?->name,
                'nationality' => $s->worker?->nationality,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'submitted_at' => $s->submitted_at?->toIso8601String(),
                'review_note' => $s->review_note,
                'payload' => $s->payload,
            ])->all(),
            'meta' => $this->listMeta(
                $page,
                $actor,
                OnboardingStatus::cases(),
                PortalScope::byWorker(OnboardingSubmission::query(), $actor),
            ),
        ]);
    }

    /** 온보딩 승인/반려 */
    public function onboardingReview(Request $request, int $submission, ReviewOnboardingAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);

        $model = PortalScope::byWorker(OnboardingSubmission::query()->whereKey($submission), $actor)->first();
        abort_if($model === null, 404, '해당 온보딩 제출물을 찾을 수 없습니다.');

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($model, $actor, OnboardingStatus::from($data['decision']), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $model->id, 'status' => $model->refresh()->status->value]]);
    }

    // ── 민원 ────────────────────────────────────────────────────────────

    public function ticketIndex(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $query = PortalScope::byWorker(SupportTicket::query(), $actor)
            ->with('worker:id,name,nationality')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value()),
            )
            ->when(
                $request->filled('type'),
                fn ($q) => $q->where('type', $request->string('type')->value()),
            )
            // 미처리를 먼저, 오래된 순
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 WHEN status = 'in_progress' THEN 1 ELSE 2 END")
            ->orderBy('created_at');

        $page = $query->paginate($this->perPage($request));
        $this->logWorkerAccess($actor, $page->pluck('worker_id')->all(), 'ticket-list');

        return response()->json([
            'data' => collect($page->items())->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'worker_id' => $t->worker_id,
                'worker_name' => $t->worker?->name,
                'type' => $t->type->value,
                'type_label' => $t->type->label(),
                'subject' => $t->subject,
                'body' => $t->body,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'created' => $t->created_at?->toIso8601String(),
            ])->all(),
            'meta' => $this->listMeta(
                $page,
                $actor,
                TicketStatus::cases(),
                PortalScope::byWorker(SupportTicket::query(), $actor),
            ),
        ]);
    }

    /** 민원 상태 변경 (+ 본인에게 담당 배정) */
    public function ticketStatus(Request $request, int $ticket, UpdateTicketStatusAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);

        $model = PortalScope::byWorker(SupportTicket::query()->whereKey($ticket), $actor)->first();
        abort_if($model === null, 404, '해당 민원을 찾을 수 없습니다.');

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,resolved'],
            'assign_to_me' => ['nullable', 'boolean'],
        ]);

        try {
            $action->execute(
                $model,
                TicketStatus::from($data['status']),
                ($data['assign_to_me'] ?? false) ? $actor : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $model->id, 'status' => $model->refresh()->status->value]]);
    }

    // ── 정착 서비스 ──────────────────────────────────────────────────────

    public function settlementIndex(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $query = PortalScope::byWorker(SettlementRequest::query(), $actor)
            ->with('worker:id,name,nationality')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value()),
            )
            ->when(
                $request->filled('type'),
                fn ($q) => $q->where('type', $request->string('type')->value()),
            )
            ->orderBy('created_at');

        $page = $query->paginate($this->perPage($request));
        $this->logWorkerAccess($actor, $page->pluck('worker_id')->all(), 'settlement-list');

        return response()->json([
            'data' => collect($page->items())->map(fn (SettlementRequest $s) => [
                'id' => $s->id,
                'worker_id' => $s->worker_id,
                'worker_name' => $s->worker?->name,
                'type' => $s->type->value,
                'type_label' => $s->type->label(),
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'created' => $s->created_at?->toIso8601String(),
            ])->all(),
            'meta' => $this->listMeta(
                $page,
                $actor,
                SettlementStatus::cases(),
                PortalScope::byWorker(SettlementRequest::query(), $actor),
            ),
        ]);
    }

    /** 정착 단계 이동 */
    public function settlementStage(Request $request, int $settlement, MoveSettlementStageAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);

        $model = PortalScope::byWorker(SettlementRequest::query()->whereKey($settlement), $actor)->first();
        abort_if($model === null, 404, '해당 정착 신청을 찾을 수 없습니다.');

        $data = $request->validate([
            'status' => ['required', 'in:received,reviewing,assigned,processing,done'],
        ]);

        try {
            $action->execute($model, SettlementStatus::from($data['status']));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $model->id, 'status' => $model->refresh()->status->value]]);
    }

    /**
     * 목록 응답의 meta 공통부.
     *
     * @param  list<\BackedEnum>  $statuses
     */
    private function listMeta($page, $actor, array $statuses, $countBase = null): array
    {
        return [
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'statuses' => array_map(
                fn ($s) => ['value' => $s->value, 'label' => $s->label()],
                $statuses,
            ),
            // 필터와 무관한 상태별 총 건수 — 목록 상단 요약 띠에 쓴다.
            'counts' => $countBase === null ? [] : $this->statusCounts($countBase),
            'can_decide' => PortalScope::canDecide($actor),
        ];
    }
}
