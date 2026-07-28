<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Notifications;

use App\Shared\Notifications\WorkerPushNotification;

/**
 * 가입 승인 알림.
 *
 * 승인 전에는 로그인 자체가 막혀 있어(WorkerStatus::canLogin) 근로자가 앱에서
 * 결과를 확인할 방법이 없다. 푸시가 사실상 유일한 통지 수단이다.
 */
class WorkerApprovedNotification extends WorkerPushNotification
{
    protected function titleKey(): string
    {
        return 'worker.push_approved_title';
    }

    protected function bodyKey(): string
    {
        return 'worker.push_approved_body';
    }

    protected function route(): array
    {
        return ['screen' => 'login'];
    }
}
