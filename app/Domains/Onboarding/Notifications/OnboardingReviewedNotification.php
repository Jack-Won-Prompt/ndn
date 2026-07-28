<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Notifications;

use App\Shared\Notifications\WorkerPushNotification;

/**
 * 온보딩 서류 검수 결과 알림 (승인/반려).
 *
 * 반려 사유는 담지 않는다 — 앱에 로그인해야 볼 수 있게 한다(§7-3).
 */
class OnboardingReviewedNotification extends WorkerPushNotification
{
    public function __construct(
        public readonly bool $approved,
        string $workerLocale = 'ko',
    ) {
        parent::__construct($workerLocale);
    }

    protected function titleKey(): string
    {
        return $this->approved
            ? 'worker.push_onboarding_approved_title'
            : 'worker.push_onboarding_rejected_title';
    }

    protected function bodyKey(): string
    {
        return 'worker.push_onboarding_body';
    }

    protected function route(): array
    {
        return ['screen' => 'onboarding'];
    }
}
