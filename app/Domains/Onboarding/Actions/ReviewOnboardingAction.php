<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Actions;

use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Onboarding\Notifications\OnboardingReviewedNotification;
use App\Domains\Onboarding\Support\OnboardingProfile;
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

        // 승인된 정보만 근로자 레코드로 승격한다 — 성별·생년월일은 매칭 조건
        // 대조(§4)에 쓰이므로 payload 에만 두면 안 된다.
        if ($decision === OnboardingStatus::Approved) {
            $this->promoteProfile($submission, $reviewer);
        }

        // 검수 결과는 근로자가 기다리는 값이라 바로 알린다. 반려 사유는 싣지 않고
        // 앱을 열어 보게 한다(§7-3 — 사유에 개인 사정이 담기기 쉽다).
        $worker = $submission->worker;
        if ($worker !== null) {
            $worker->notify(new OnboardingReviewedNotification(
                approved: $decision === OnboardingStatus::Approved,
                workerLocale: $worker->locale ?? 'ko',
            ));
        }

        return $submission;
    }

    /** 승인 시 payload 의 성별·생년월일을 workers 컬럼에 반영하고 기록을 남긴다. */
    private function promoteProfile(OnboardingSubmission $submission, User $reviewer): void
    {
        $worker = $submission->worker;

        if ($worker === null) {
            return;
        }

        $changed = OnboardingProfile::applyTo($worker, $submission->payload ?? []);

        if ($changed === []) {
            return;
        }

        // 어떤 항목이 반영됐는지만 남긴다 — 값 자체는 남기지 않는다(§7-1).
        activity('worker-profile')
            ->performedOn($worker)
            ->causedBy($reviewer)
            ->withProperties([
                'fields' => $changed,
                'submission_id' => $submission->id,
            ])
            ->log('온보딩 승인으로 근로자 정보 갱신');
    }
}
