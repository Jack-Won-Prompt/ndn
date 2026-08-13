<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Actions\AdvanceWorkerExitAction;
use App\Domains\Support\Actions\OpenWorkerExitAction;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Enums\TicketType;
use App\Domains\Support\Enums\WorkerExitReason;
use App\Domains\Support\Enums\WorkerExitStatus;
use App\Domains\Support\Enums\WorkerExitType;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\WorkerExit;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * 콘솔 — 조기 귀국·이탈 관리 (업무흐름 §8).
 *
 * 판단은 전부 Action 두 개가 한다. 여기서는 화면에 필요한 모양으로 골라 담고
 * 실패 사유를 그대로 돌려준다.
 */
class WorkerExitController extends Controller
{
    /**
     * 사건 목록 — 진행 중인 것이 위로, 오래된 것부터.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(): array
    {
        $exits = WorkerExit::query()
            ->with(['worker:id,name,nationality,status', 'placement.farm:id,name', 'decider:id,name', 'creator:id,name'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        self::logAccess($exits->pluck('worker_id')->all());

        return $exits
            // 진행 중인 건이 항상 위. 그 안에서는 오래 끌고 있는 것부터.
            ->sortBy(fn (WorkerExit $e) => [$e->status->isOpen() ? 0 : 1, $e->occurred_on?->timestamp ?? 0])
            ->values()
            ->map(fn (WorkerExit $e) => self::present($e))
            ->all();
    }

    /** 사이드바 배지 — 아직 손대야 하는 건수. */
    public static function openCount(): int
    {
        return WorkerExit::query()->open()->count();
    }

    /**
     * 사건을 만들 수 있는 근로자 — 승인 전·이미 귀국한 사람은 뺀다.
     *
     * @return array<int, array{value:int,label:string}>
     */
    public static function workerOptions(): array
    {
        return Worker::query()
            ->whereIn('status', [
                WorkerStatus::Active->value,
                WorkerStatus::Inactive->value,
                WorkerStatus::Absconded->value,
            ])
            ->with(['placements.farm:id,name'])
            ->orderBy('name')
            ->limit(2000)
            ->get(['id', 'name', 'nationality', 'status'])
            ->map(function (Worker $w) {
                $farm = $w->currentPlacement()?->farm;

                return [
                    'value' => $w->id,
                    'label' => $w->name.' ('.$w->nationality.')'
                        .($farm ? ' · '.$farm->name : ' · 미배정')
                        .' · '.$w->status->label(),
                ];
            })
            ->all();
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return WorkerExitType::options();
    }

    /** @return array<string, string> */
    public static function reasonOptions(): array
    {
        return WorkerExitReason::options();
    }

    /** 사건 열기 — 조기 귀국 신청 접수 또는 이탈 인지. */
    public function store(Request $request, OpenWorkerExitAction $action): JsonResponse
    {
        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'type' => ['required', Rule::enum(WorkerExitType::class)],
            'reason' => ['nullable', Rule::enum(WorkerExitReason::class)],
            'reason_detail' => ['nullable', 'string', 'max:2000'],
            'occurred_on' => ['required', 'date'],
            'noticed_on' => ['nullable', 'date'],
            'support_ticket_id' => ['nullable', 'integer', 'exists:support_tickets,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $worker = Worker::findOrFail($data['worker_id']);
        $type = WorkerExitType::from($data['type']);

        $data['reason'] = filled($data['reason'] ?? null)
            ? WorkerExitReason::from($data['reason'])
            : null;

        try {
            $exit = $action->execute($worker, $type, $data, Auth::user());
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'id' => $exit->id]);
    }

    /** 상세 — 어떤 버튼을 누를 수 있는지까지 함께 준다. */
    public function show(WorkerExit $workerExit): JsonResponse
    {
        $workerExit->load(['worker', 'placement.farm', 'ticket', 'decider', 'creator']);

        $workerExit->worker?->recordAccessBy(Auth::user(), 'worker-exit');

        return response()->json(self::present($workerExit) + [
            'ticket' => $workerExit->ticket === null ? null : [
                'id' => $workerExit->ticket->id,
                'subject' => $workerExit->ticket->subject,
                'body' => $workerExit->ticket->body,
                'status' => $workerExit->ticket->status->label(),
            ],
            'next' => array_map(
                fn (WorkerExitStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $workerExit->nextStatuses(),
            ),
            // 이탈 확정으로 넘어갈 때만 신고 칸을 보여 주기 위한 힌트
            'needs_report' => $workerExit->type === WorkerExitType::Absconded,
            'needs_departure' => $workerExit->type === WorkerExitType::EarlyReturn,
        ]);
    }

    /** 상태 전이 — 결정을 내린다. */
    public function advance(Request $request, WorkerExit $workerExit, AdvanceWorkerExitAction $action): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(WorkerExitStatus::class)],
            'reason' => ['nullable', Rule::enum(WorkerExitReason::class)],
            'reason_detail' => ['nullable', 'string', 'max:2000'],
            'departed_on' => ['nullable', 'date'],
            'reported' => ['nullable', 'boolean'],
            'reported_on' => ['nullable', 'date'],
            'report_ref' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['reason'] = filled($data['reason'] ?? null)
            ? WorkerExitReason::from($data['reason'])
            : null;

        try {
            $action->execute(
                $workerExit,
                WorkerExitStatus::from($data['status']),
                $data,
                Auth::user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * 아직 사건으로 열리지 않은 조기 귀국 민원 — 등록 화면에서 골라 잇는다.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pendingTickets(): array
    {
        return SupportTicket::query()
            ->with('worker:id,name')
            ->where('type', TicketType::EarlyReturn->value)
            ->where('status', '!=', TicketStatus::Resolved->value)
            ->whereDoesntHave('worker.exits', fn ($q) => $q->open())
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'worker_id' => $t->worker_id,
                'worker' => $t->worker?->name ?? '—',
                'subject' => $t->subject,
                'date' => LocalTime::format($t->created_at),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private static function present(WorkerExit $e): array
    {
        return [
            'id' => $e->id,
            'worker_id' => $e->worker_id,
            'worker' => $e->worker?->name ?? '—',
            'nationality' => $e->worker?->nationality ?? '—',
            'worker_status' => $e->worker?->status->label() ?? '—',
            'farm' => $e->placement?->farm?->name ?? '—',
            'type' => $e->type->value,
            'type_label' => $e->type->label(),
            'status' => $e->status->value,
            'status_label' => $e->status->label(),
            'tone' => $e->status->tone(),
            'open' => $e->status->isOpen(),
            'reason' => $e->reason->value,
            'reason_label' => $e->reason->label(),
            'reason_detail' => $e->reason_detail,
            'occurred_label' => $e->type->occurredLabel(),
            'occurred_on' => $e->occurred_on?->toDateString() ?? '—',
            'noticed_on' => $e->noticed_on?->toDateString(),
            // 연락두절 일수 — 이탈 건에서만 뜬다
            'days' => $e->daysUnreachable(),
            'departed_on' => $e->departed_on?->toDateString(),
            'reported' => $e->reported,
            'reported_on' => $e->reported_on?->toDateString(),
            'report_ref' => $e->report_ref,
            'decided_at' => $e->decided_at === null ? null : LocalTime::format($e->decided_at),
            'decided_by' => $e->decider?->name,
            'created_by' => $e->creator?->name ?? '—',
            'note' => $e->note,
            'ticket_id' => $e->support_ticket_id,
        ];
    }

    /**
     * 근로자 이름이 화면에 뜬 기록(§7-6). 목록은 묶어서 한 줄만 남긴다.
     *
     * @param  array<int, int|null>  $workerIds
     */
    private static function logAccess(array $workerIds): void
    {
        $ids = array_values(array_unique(array_filter($workerIds)));

        if ($ids === [] || Auth::user() === null) {
            return;
        }

        activity('personal-data-access')
            ->causedBy(Auth::user())
            ->withProperties(['reason' => 'worker-exit-list', 'worker_ids' => $ids, 'count' => count($ids)])
            ->log('개인정보 열람: worker-exit-list');
    }
}
