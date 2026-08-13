<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Matching\Actions\CancelPlacementAction;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Enums\WorkerExitStatus;
use App\Domains\Support\Models\WorkerExit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 조기 귀국·이탈 건의 상태를 옮긴다 (업무흐름 §8).
 *
 * 이 Action 이 하는 일의 핵심은 **한 번의 결정이 세 곳에 동시에 반영되게** 하는 것이다.
 *   ① 사건 기록      — 결정자·결정 시각·사유
 *   ② 근로자 계정     — 이탈/귀국/복귀
 *   ③ 농가 배정       — 사람이 빠졌으면 자리를 비워야 다음 사람을 넣는다
 *
 * 셋을 따로 하면 반드시 어긋난다. 실제로 지금까지 status 만 손으로 바꾸는 바람에
 * 귀국한 사람이 농가 정원을 계속 차지하고 있었다.
 */
class AdvanceWorkerExitAction
{
    public function __construct(private readonly CancelPlacementAction $cancelPlacement) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException 갈 수 없는 상태일 때
     */
    public function execute(WorkerExit $exit, WorkerExitStatus $to, array $data, User $actor): WorkerExit
    {
        if (! $exit->status->canTransitionTo($to, $exit->type)) {
            throw new RuntimeException(
                "{$exit->status->label()} 상태에서는 '{$to->label()}' 로 넘어갈 수 없습니다."
            );
        }

        return DB::transaction(function () use ($exit, $to, $data, $actor) {
            $from = $exit->status;

            $exit->forceFill(array_filter([
                'status' => $to,
                'decided_at' => now(),
                'decided_by' => $actor->id,
                // 사유는 결정 시점에 확정되는 경우가 많다 (이탈은 인지 때 '미상' 이었다)
                'reason' => $data['reason'] ?? $exit->reason,
                'reason_detail' => $data['reason_detail'] ?? $exit->reason_detail,
                'departed_on' => $data['departed_on'] ?? $exit->departed_on,
                'reported' => $data['reported'] ?? $exit->reported,
                'reported_on' => $data['reported_on'] ?? $exit->reported_on,
                'report_ref' => $data['report_ref'] ?? $exit->report_ref,
                'note' => $data['note'] ?? $exit->note,
            ], fn ($v) => $v !== null))->save();

            $this->applyToWorker($exit, $to, $actor);

            activity('worker-exit')
                ->performedOn($exit)
                ->causedBy($actor)
                ->withProperties([
                    'worker_id' => $exit->worker_id,
                    'type' => $exit->type->value,
                    'from' => $from->value,
                    'to' => $to->value,
                    'reason' => $exit->reason->value,
                ])
                ->log("{$exit->type->label()} — {$to->label()}");

            return $exit->refresh();
        });
    }

    /** 결정을 근로자 계정·배정·민원에 반영한다. */
    private function applyToWorker(WorkerExit $exit, WorkerExitStatus $to, User $actor): void
    {
        $worker = $exit->worker;

        if ($worker === null) {
            return;
        }

        match ($to) {
            // 출국했다 — 계정을 닫고 자리를 비운다
            WorkerExitStatus::Completed => $this->close($exit, WorkerStatus::Returned, '조기 귀국', $actor),

            // 이탈 확정 — 계정을 닫고 자리를 비운다. 신고는 담당자가 따로 한다.
            WorkerExitStatus::Confirmed => $this->close($exit, WorkerStatus::Absconded, '이탈 확정', $actor),

            // 계속 근무한다 — 되돌린다
            WorkerExitStatus::Rejected, WorkerExitStatus::Recovered => $this->reactivate($worker),

            // 승인·연락두절은 아직 사람이 빠진 게 아니다. 계정을 건드리지 않는다.
            default => null,
        };

        $this->closeTicket($exit, $to);
    }

    /** 계정을 닫고 진행 중인 배정을 취소한다. */
    private function close(WorkerExit $exit, WorkerStatus $status, string $reason, User $actor): void
    {
        $worker = $exit->worker;
        $worker->forceFill(['status' => $status])->save();

        // 제안·확정 중인 배정을 모두 정리한다. 남겨 두면 농가 정원이 계속 잠긴다.
        $placements = $worker->placements()
            ->whereIn('status', [PlacementStatus::Proposed->value, PlacementStatus::Confirmed->value])
            ->get();

        foreach ($placements as $placement) {
            $this->cancelPlacement->execute($placement, $actor, "{$reason} (건 #{$exit->id})");
        }
    }

    /** 반려·복귀 — 재직으로 되돌린다. 이미 귀국 처리된 사람은 건드리지 않는다. */
    private function reactivate(Worker $worker): void
    {
        if ($worker->status === WorkerStatus::Returned) {
            return;
        }

        $worker->forceFill(['status' => WorkerStatus::Active])->save();
    }

    /**
     * 앱에서 올라온 민원을 함께 닫는다.
     *
     * 결정이 났는데 민원이 '처리 중'으로 남아 있으면 근로자는 앱에서 아무 답도
     * 못 본 것과 같다.
     */
    private function closeTicket(WorkerExit $exit, WorkerExitStatus $to): void
    {
        $ticket = $exit->ticket;

        if ($ticket === null || $ticket->status === TicketStatus::Resolved) {
            return;
        }

        // 결정이 끝난 상태에서만 닫는다. 승인은 아직 출국 전이라 열어 둔다.
        if (in_array($to, [WorkerExitStatus::Completed, WorkerExitStatus::Rejected], true)) {
            $ticket->forceFill([
                'status' => TicketStatus::Resolved,
                'resolved_at' => now(),
            ])->save();
        }
    }
}
