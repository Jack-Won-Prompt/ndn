<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Notifications;

use App\Shared\Notifications\WorkerPushNotification;

/**
 * 선발 합격 알림 (업무흐름 §2).
 *
 * 합격과 동시에 계정이 활성화되므로 "합격했고, 이제 로그인할 수 있다"를 한 번에
 * 알린다. 승인만 났을 때 쓰는 WorkerApprovedNotification 과 문구가 다르다 —
 * 근로자에게는 '승인'보다 '합격'이 훨씬 큰 소식이다.
 */
class WorkerPassedNotification extends WorkerPushNotification
{
    protected function titleKey(): string
    {
        return 'worker.push_passed_title';
    }

    protected function bodyKey(): string
    {
        return 'worker.push_passed_body';
    }

    protected function route(): array
    {
        return ['screen' => 'login'];
    }
}
