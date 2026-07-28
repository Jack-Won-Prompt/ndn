<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Notifications\WorkerRejectedNotification;
use App\Models\User;

/**
 * 근로자 가입 거절 (관리자). pending → rejected 전환 + 감사 로그(§7-6).
 *
 * 거절 계정은 로그인할 수 없다. 재신청은 담당자 안내에 따른다.
 */
class RejectWorkerAction
{
    public function execute(Worker $worker, User $admin, ?string $reason = null): Worker
    {
        if (! $worker->status->isPending()) {
            throw new \RuntimeException('승인 대기 상태의 계정만 거절할 수 있습니다.');
        }

        $worker->forceFill(['status' => WorkerStatus::Rejected])->save();

        activity('worker-account')
            ->performedOn($worker)
            ->causedBy($admin)
            ->withProperties(['action' => 'reject', 'reason' => $reason])
            ->log('근로자 가입 거절');

        // 사유는 싣지 않는다 — 잠금화면에 뜨므로 담당자 문의로만 안내한다(§7-3).
        $worker->notify(new WorkerRejectedNotification($worker->locale ?? 'ko'));

        return $worker;
    }
}
