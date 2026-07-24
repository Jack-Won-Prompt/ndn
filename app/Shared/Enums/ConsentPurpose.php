<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * 개인정보 활용 동의 목적 (CLAUDE.md §7-4).
 *
 * ConsentRecord 는 이 목적별로 행을 분리 저장한다. 제3자(대리점·제휴사) 제공은
 * 해당 목적의 동의가 존재해야만 Policy 를 통과한다.
 */
enum ConsentPurpose: string
{
    case OnboardingReview = 'onboarding_review';  // 온보딩 정보 검수
    case SettlementService = 'settlement_service'; // 정착 서비스(통장·보험·통신) 처리
    case ThirdPartyAgency = 'third_party_agency';  // 제휴 대리점 제3자 제공
    case Notification = 'notification';        // 알림 발송

    public function label(): string
    {
        return match ($this) {
            self::OnboardingReview => '온보딩 정보 검수',
            self::SettlementService => '정착 서비스 처리',
            self::ThirdPartyAgency => '제3자 제공(대리점)',
            self::Notification => '알림 발송',
        };
    }
}
