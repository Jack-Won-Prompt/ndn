<?php

declare(strict_types=1);

namespace App\Domains\Matching\Enums;

/**
 * 매칭(배정) 상태 (CLAUDE.md §5, 업무흐름 §4).
 *
 * proposed(추천/제안) → confirmed(확정) → 이후 입국 단계는 ArrivalStatus 가 잇는다.
 * cancelled 는 확정 전후 어디서든 취소된 경우.
 */
enum PlacementStatus: string
{
    case Proposed = 'proposed';    // 매칭 제안 (농가·시청 확인 대기)
    case Confirmed = 'confirmed';  // 배정 확정 — 입국 준비 시작
    case Cancelled = 'cancelled';  // 취소

    public function label(): string
    {
        return match ($this) {
            self::Proposed => '배정 제안',
            self::Confirmed => '배정 확정',
            self::Cancelled => '취소',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Proposed => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
