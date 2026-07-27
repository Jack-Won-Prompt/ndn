<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

/**
 * SOS 대응 상태 (업무흐름 §8, §7 — 긴급 24시간 대응).
 *
 * open(접수) → acknowledged(담당자 확인) → closed(대응 완료)
 * 접수 즉시 종료(오발신 등)도 허용한다.
 */
enum SosStatus: string
{
    case Open = 'open';                  // 접수 — 아직 아무도 확인하지 않음
    case Acknowledged = 'acknowledged';  // 담당자 확인 — 대응 중
    case Closed = 'closed';              // 대응 완료

    public function label(): string
    {
        return match ($this) {
            self::Open => '미확인',
            self::Acknowledged => '확인·대응 중',
            self::Closed => '대응 완료',
        };
    }

    /** 아직 사람이 확인하지 않은 건인지 — 상황판에서 가장 위로 올린다. */
    public function needsAttention(): bool
    {
        return $this === self::Open;
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Acknowledged, self::Closed],
            self::Acknowledged => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
