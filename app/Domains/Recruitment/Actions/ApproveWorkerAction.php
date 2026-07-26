<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;

/**
 * 근로자 가입 승인 (관리자). pending → active 전환 + 승인자·시각 기록.
 *
 * 승인 후에야 앱 로그인이 가능해진다(LoginWorkerAction). 감사 로그를 남긴다(§7-6).
 */
class ApproveWorkerAction
{
    public function execute(Worker $worker, User $admin): Worker
    {
        if (! $worker->status->isPending()) {
            throw new \RuntimeException('승인 대기 상태의 계정만 승인할 수 있습니다.');
        }

        $worker->forceFill([
            'status' => WorkerStatus::Active,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ])->save();

        activity('worker-account')
            ->performedOn($worker)
            ->causedBy($admin)
            ->withProperties(['action' => 'approve'])
            ->log('근로자 가입 승인');

        return $worker;
    }
}
