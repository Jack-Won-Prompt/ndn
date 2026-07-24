<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Policies;

use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Models\User;
use App\Shared\Enums\UserRole;

/**
 * 온보딩 제출물 인가 (CLAUDE.md §11).
 *
 * - 검수(승인/반려)는 NDN 관리자만.
 * - 열람은 NDN 관리자. (근로자 본인 열람은 앱 API 의 worker 스코프에서 별도 처리)
 */
class OnboardingSubmissionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isRole(UserRole::NdnAdmin) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false; // NDN 관리자만(before 통과). 그 외 역할은 목록 불가.
    }

    public function view(User $user, OnboardingSubmission $submission): bool
    {
        return false;
    }

    public function review(User $user, OnboardingSubmission $submission): bool
    {
        return false; // NDN 관리자만(before 통과).
    }
}
