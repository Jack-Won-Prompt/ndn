<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

/**
 * SR 처리 상태 (접수 → 처리 중 → 적용 완료 / 반려).
 *
 * '적용 완료' 로 바뀌는 순간 등록자에게 이메일이 나간다(ChangeServiceRequestStatusAction).
 * '반려' 도 종료 상태이지만 알림은 보내지 않는다 — 담당자가 답글로 사유를 남긴다.
 */
enum ServiceRequestStatus: string
{
    case Received = 'received';       // 접수
    case InProgress = 'in_progress';  // 처리 중
    case Completed = 'completed';     // 적용 완료 (종료)
    case Rejected = 'rejected';       // 반려 (종료)

    public function label(): string
    {
        return match ($this) {
            self::Received => '접수',
            self::InProgress => '처리 중',
            self::Completed => '적용 완료',
            self::Rejected => '반려',
        };
    }

    /** 종료된 상태인가 (더 이상 조치가 필요 없음). */
    public function isClosed(): bool
    {
        return $this === self::Completed || $this === self::Rejected;
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::InProgress, self::Completed, self::Rejected],
            self::InProgress => [self::Completed, self::Rejected],
            // 종료 건은 되돌릴 수 있게 둔다 — 잘못 종료한 SR 을 다시 여는 실무 요구가 있다.
            self::Completed, self::Rejected => [self::InProgress],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label()],
            self::cases(),
        );
    }
}
