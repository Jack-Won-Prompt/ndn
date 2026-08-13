<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Enums\TicketType;
use App\Domains\Support\Enums\WorkerExitReason;
use App\Domains\Support\Enums\WorkerExitType;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\WorkerExit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 조기 귀국 신청·이탈 인지를 사건으로 연다 (업무흐름 §8).
 *
 * 사건을 여는 것만으로는 근로자 상태를 바꾸지 않는다. 조기 귀국은 아직 결정
 * 전이고, 연락두절은 이탈로 확정된 것이 아니다. 상태는
 * AdvanceWorkerExitAction 이 결정을 내릴 때만 움직인다.
 *
 * 예외가 하나 있다: 연락두절이면 **앱 로그인을 곧바로 막는다.** 소재가
 * 불명한 계정이 그대로 살아 있으면 안 된다. 소재가 확인되면 되돌린다.
 */
class OpenWorkerExitAction
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException 이미 진행 중인 같은 유형의 건이 있을 때
     */
    public function execute(Worker $worker, WorkerExitType $type, array $data, User $actor): WorkerExit
    {
        return DB::transaction(function () use ($worker, $type, $data, $actor) {
            // 같은 사건을 두 명이 각각 열면 통계가 두 배가 된다.
            $existing = WorkerExit::query()
                ->where('worker_id', $worker->id)
                ->where('type', $type->value)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new RuntimeException(
                    "{$worker->name} 님은 이미 진행 중인 {$type->label()} 건이 있습니다 (#{$existing->id})."
                );
            }

            if (in_array($worker->status, [WorkerStatus::Pending, WorkerStatus::Rejected], true)) {
                throw new RuntimeException('승인 전 근로자에게는 조기 귀국·이탈 건을 만들 수 없습니다.');
            }

            $exit = WorkerExit::create([
                'worker_id' => $worker->id,
                // 어느 배정에서 빠졌는지 남긴다. 미배정 상태에서 이탈하는 경우도 있다.
                'placement_id' => $worker->currentPlacement()?->id,
                'support_ticket_id' => $this->linkTicket($worker, $type, $data),
                'type' => $type,
                'status' => $type->initialStatus(),
                // 이탈은 인지 시점에 사유를 모른다. 억지로 고르게 하지 않는다.
                'reason' => $data['reason'] ?? WorkerExitReason::Unknown,
                'reason_detail' => $data['reason_detail'] ?? null,
                'occurred_on' => $data['occurred_on'],
                'noticed_on' => $type === WorkerExitType::Absconded
                    ? ($data['noticed_on'] ?? now()->toDateString())
                    : null,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);

            if ($type === WorkerExitType::Absconded) {
                $worker->forceFill(['status' => WorkerStatus::Inactive])->save();
            }

            activity('worker-exit')
                ->performedOn($exit)
                ->causedBy($actor)
                ->withProperties([
                    'worker_id' => $worker->id,
                    'type' => $type->value,
                    'reason' => $exit->reason->value,
                ])
                ->log("{$type->label()} 건 등록");

            return $exit;
        });
    }

    /**
     * 앱에서 올라온 조기 귀국 민원과 잇는다.
     *
     * 담당자가 지정하지 않으면 그 근로자의 미처리 조기 귀국 민원을 알아서 붙인다.
     * 앱에서 신청했는데 콘솔 건과 따로 놀면 근로자는 답을 못 받는다.
     *
     * @param  array<string, mixed>  $data
     */
    private function linkTicket(Worker $worker, WorkerExitType $type, array $data): ?int
    {
        if (filled($data['support_ticket_id'] ?? null)) {
            return (int) $data['support_ticket_id'];
        }

        if ($type !== WorkerExitType::EarlyReturn) {
            return null;
        }

        return SupportTicket::query()
            ->where('worker_id', $worker->id)
            ->where('type', TicketType::EarlyReturn->value)
            ->where('status', '!=', TicketStatus::Resolved->value)
            ->latest('id')
            ->value('id');
    }
}
