<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Enums;

/**
 * 셀프 온보딩 제출물 상태 (CLAUDE.md §5).
 *
 * draft → submitted(근로자 제출) → under_review(NDN 검수 중)
 *        → approved(승인) 또는 rejected(반려 → 재작성)
 */
enum OnboardingStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '작성 중',
            self::Submitted => '제출됨',
            self::UnderReview => '검수 중',
            self::Approved => '승인',
            self::Rejected => '반려',
        };
    }

    public function isEditableByWorker(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::UnderReview, self::Rejected],
            self::UnderReview => [self::Approved, self::Rejected],
            self::Approved => [],
            self::Rejected => [self::Submitted],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
