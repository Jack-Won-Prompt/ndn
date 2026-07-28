<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Notifications;

use App\Shared\Notifications\WorkerPushNotification;

/**
 * 가입 거절 알림.
 *
 * 사유는 담지 않는다 — 거절 사유에는 개인 사정이 담기기 쉬운데 잠금화면에
 * 노출되면 안 된다. 담당자 문의로만 안내한다(§7-3).
 */
class WorkerRejectedNotification extends WorkerPushNotification
{
    protected function titleKey(): string
    {
        return 'worker.push_rejected_title';
    }

    protected function bodyKey(): string
    {
        return 'worker.push_rejected_body';
    }
}
