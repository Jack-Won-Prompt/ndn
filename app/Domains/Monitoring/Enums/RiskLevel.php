<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Enums;

/**
 * 이탈 리스크 등급 (CLAUDE.md §5, 업무흐름 §7).
 *
 * 월별 인터뷰 6개 항목의 부정 응답 수(행동 신호 기반, 위치 추적 미사용)로 산출한다.
 */
enum RiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => '낮음',
            self::Medium => '주의',
            self::High => '고위험',
        };
    }

    /**
     * 부정 신호 개수(0~6)로 등급 산정.
     * 0 → 낮음, 1~2 → 주의, 3+ → 고위험.
     */
    public static function fromNegativeCount(int $negatives): self
    {
        return match (true) {
            $negatives >= 3 => self::High,
            $negatives >= 1 => self::Medium,
            default => self::Low,
        };
    }
}
