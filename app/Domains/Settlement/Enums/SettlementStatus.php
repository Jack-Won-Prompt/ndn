<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Enums;

/**
 * 정착 서비스 처리 상태 — 칸반 단계 (업무흐름 §6-3).
 * 접수 → 서류 검수 → 대리점 전달 → 처리 중 → 완료.
 */
enum SettlementStatus: string
{
    case Received = 'received';   // 접수
    case Reviewing = 'reviewing';  // 서류 검수
    case Assigned = 'assigned';   // 대리점 전달(배정)
    case Processing = 'processing'; // 처리 중
    case Done = 'done';       // 완료

    public function label(): string
    {
        return match ($this) {
            self::Received => '접수',
            self::Reviewing => '서류 검수',
            self::Assigned => '대리점 전달',
            self::Processing => '처리 중',
            self::Done => '완료',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::Reviewing, self::Assigned],
            self::Reviewing => [self::Assigned],
            self::Assigned => [self::Processing],
            self::Processing => [self::Done],
            self::Done => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
