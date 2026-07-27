<?php

declare(strict_types=1);

namespace App\Domains\Arrival\Enums;

/**
 * 입국·이송 단계 (업무흐름 §5).
 *
 * 배정 확정(PlacementStatus::Confirmed) 이후의 흐름을 잇는다.
 *   scheduled(입국 예정) → arrived(공항 도착) → picked_up(픽업 완료)
 *   → handed_over(농가 인계 완료)
 *
 * 각 단계는 앞으로만 진행하며 건너뛸 수 없다. 잘못 넘긴 경우를 위해 한 단계
 * 되돌리기는 별도 권한(NDN 관리자)으로만 허용한다.
 */
enum ArrivalStatus: string
{
    case Scheduled = 'scheduled';       // 입국 예정 (항공편 등록됨)
    case Arrived = 'arrived';           // 공항 도착 확인
    case PickedUp = 'picked_up';        // 픽업 차량 탑승 완료
    case HandedOver = 'handed_over';    // 농가 인계 완료

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => '입국 예정',
            self::Arrived => '공항 도착',
            self::PickedUp => '픽업 완료',
            self::HandedOver => '농가 인계 완료',
        };
    }

    /** 진행 순서(0부터). 화면의 스텝 표시·정렬에 쓴다. */
    public function step(): int
    {
        return match ($this) {
            self::Scheduled => 0,
            self::Arrived => 1,
            self::PickedUp => 2,
            self::HandedOver => 3,
        };
    }

    /** 바로 다음 단계 (마지막이면 null) */
    public function next(): ?self
    {
        return match ($this) {
            self::Scheduled => self::Arrived,
            self::Arrived => self::PickedUp,
            self::PickedUp => self::HandedOver,
            self::HandedOver => null,
        };
    }

    /** 한 단계 이전 (첫 단계면 null) */
    public function previous(): ?self
    {
        return match ($this) {
            self::Scheduled => null,
            self::Arrived => self::Scheduled,
            self::PickedUp => self::Arrived,
            self::HandedOver => self::PickedUp,
        };
    }

    /** 단계를 건너뛰지 않는지 — 다음 단계로만 전진 가능 */
    public function canTransitionTo(self $target): bool
    {
        return $this->next() === $target;
    }

    public function isComplete(): bool
    {
        return $this === self::HandedOver;
    }
}
