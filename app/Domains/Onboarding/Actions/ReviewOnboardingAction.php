<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Actions;

use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Models\User;
use InvalidArgumentException;
use RuntimeException;

/**
 * NDN 관리자가 온보딩 제출물을 검수(승인/반려)한다.
 */
class ReviewOnboardingAction
{
    public function execute(
        OnboardingSubmission $submission,
        User $reviewer,
        OnboardingStatus $decision,
        ?string $note = null,
    ): OnboardingSubmission {
        if (! in_array($decision, [OnboardingStatus::Approved, OnboardingStatus::Rejected], true)) {
            throw new InvalidArgumentException('검수 결정은 승인 또는 반려만 가능합니다.');
        }

        // submitted → under_review 를 거쳐 결정. 여기서는 검수 착수+결정을 한 번에 처리.
        $current = $submission->status;
        $viaReview = $current === OnboardingStatus::Submitted
            ? OnboardingStatus::UnderReview
            : $current;

        if (! $viaReview->canTransitionTo($decision)) {
            throw new RuntimeException("검수할 수 없는 상태입니다: {$current->value}");
        }

        $submission->update([
            'status' => $decision,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $submission;
    }
}
