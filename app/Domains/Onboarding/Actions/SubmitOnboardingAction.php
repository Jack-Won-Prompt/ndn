<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Actions;

use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use RuntimeException;

/**
 * 근로자가 셀프 온보딩 제출물을 제출한다 (draft/rejected → submitted).
 */
class SubmitOnboardingAction
{
    public function execute(OnboardingSubmission $submission): OnboardingSubmission
    {
        if (! $submission->status->canTransitionTo(OnboardingStatus::Submitted)) {
            throw new RuntimeException("제출할 수 없는 상태입니다: {$submission->status->value}");
        }

        $submission->update([
            'status' => OnboardingStatus::Submitted,
            'submitted_at' => now(),
            // 재제출 시 이전 검수 결과 초기화
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ]);

        return $submission;
    }
}
