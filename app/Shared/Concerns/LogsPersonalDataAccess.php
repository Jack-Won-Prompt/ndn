<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use App\Models\User;

/**
 * 개인정보 열람 감사 (CLAUDE.md §7-6).
 *
 * Worker 개인정보를 읽는 관리자 화면·API 는 "누가, 언제, 어떤 worker_id 를 조회했는지"
 * activitylog 에 남긴다. 변경 로그는 spatie 가 자동으로 남기지만 "조회"는 명시적으로
 * 이 메서드를 호출해 기록해야 한다.
 */
trait LogsPersonalDataAccess
{
    /**
     * 이 레코드의 개인정보가 열람되었음을 감사 로그에 기록한다.
     */
    public function recordAccessBy(User $user, string $reason = 'view'): void
    {
        activity('personal-data-access')
            ->performedOn($this)
            ->causedBy($user)
            ->withProperties([
                'reason' => $reason,
                'subject' => class_basename($this),
            ])
            ->log("개인정보 열람: {$reason}");
    }
}
