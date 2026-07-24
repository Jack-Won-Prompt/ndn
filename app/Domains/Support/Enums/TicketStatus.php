<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

/**
 * 민원 처리 상태 (업무흐름 §8: 접수 → 처리 중 → 완료).
 */
enum TicketStatus: string
{
    case Open = 'open';        // 접수
    case InProgress = 'in_progress'; // 처리 중
    case Resolved = 'resolved';    // 완료

    public function label(): string
    {
        return match ($this) {
            self::Open => '접수',
            self::InProgress => '처리 중',
            self::Resolved => '완료',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Resolved],
            self::InProgress => [self::Resolved],
            self::Resolved => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
