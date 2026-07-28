<?php

declare(strict_types=1);

namespace App\Domains\Matching\Notifications;

use App\Shared\Notifications\WorkerPushNotification;

/**
 * 배정 확정 알림.
 *
 * 농가 이름은 담지 않는다 — 어디에 배정됐는지는 본인 외에는 알 이유가 없고,
 * 잠금화면에 뜨면 그대로 노출된다. 앱을 열면 배정 화면에서 볼 수 있다.
 */
class PlacementConfirmedNotification extends WorkerPushNotification
{
    protected function titleKey(): string
    {
        return 'worker.push_placement_title';
    }

    protected function bodyKey(): string
    {
        return 'worker.push_placement_body';
    }

    protected function route(): array
    {
        return ['screen' => 'placement'];
    }
}
