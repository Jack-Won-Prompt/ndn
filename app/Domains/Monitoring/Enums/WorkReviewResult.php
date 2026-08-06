<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Enums;

/**
 * 종합 점검 결과 (근무상태 종합 점검표 §10).
 *
 * 점검자가 직접 고른다. 항목별 응답으로 자동 산정하지 않는다 —
 * 표에 드러나지 않는 현장 판단이 들어가는 자리다.
 */
enum WorkReviewResult: string
{
    case Excellent = 'excellent';
    case Good = 'good';
    case Fair = 'fair';
    case NeedsImprovement = 'needs_improvement';
    case SpecialCare = 'special_care';

    public function label(): string
    {
        return match ($this) {
            self::Excellent => '매우 우수',
            self::Good => '우수',
            self::Fair => '보통',
            self::NeedsImprovement => '개선 필요',
            self::SpecialCare => '특별관리 대상',
        };
    }
}
