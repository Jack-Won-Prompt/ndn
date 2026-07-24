<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Enums;

/**
 * 후보자 상태 (CLAUDE.md §5, 업무흐름 §2).
 *
 * 면접 평가 결과로 분류: 합격 / 보류(대기열) / 불합격.
 */
enum CandidateStatus: string
{
    case Applied = 'applied';   // 지원(면접 전)
    case Passed = 'passed';    // 합격
    case Held = 'held';      // 보류(대기열)
    case Rejected = 'rejected';  // 불합격

    public function label(): string
    {
        return match ($this) {
            self::Applied => '지원',
            self::Passed => '합격',
            self::Held => '보류',
            self::Rejected => '불합격',
        };
    }

    /** 셀프 온보딩 초대 대상(합격·보류) 여부 (§2-3) */
    public function isOnboardingEligible(): bool
    {
        return in_array($this, [self::Passed, self::Held], true);
    }
}
