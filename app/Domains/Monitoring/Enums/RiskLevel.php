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

    /**
     * 근무상태 종합 점검표 점수로 등급 산정.
     *
     * 항목이 40개가 넘어 부정 신호 개수를 그대로 쓰면 한두 개만 미흡해도 고위험이
     * 된다. 나쁨 2점·보통 1점으로 매긴 합계에 문턱을 둔다.
     * 이탈 가능성·임금 체불처럼 그 자체로 중대한 신호는 Action 이 곧장 고위험으로
     * 올린다 — 점수와 상관없이.
     */
    public static function fromReviewScore(int $score): self
    {
        return match (true) {
            $score >= 8 => self::High,
            $score >= 3 => self::Medium,
            default => self::Low,
        };
    }
}
