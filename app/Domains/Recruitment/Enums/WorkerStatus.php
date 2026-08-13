<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Enums;

/**
 * 근로자 계정 상태 (CLAUDE.md §5, §7-7).
 *
 * 가입은 관리자 승인제: 셀프 가입 직후 pending → 관리자 승인 시 active.
 * 앱 로그인은 active 만 허용한다(LoginWorkerAction).
 */
enum WorkerStatus: string
{
    case Pending = 'pending';    // 가입 신청 — 승인 대기
    case Active = 'active';      // 재직(활성) — 로그인·업무 가능
    case Inactive = 'inactive';  // 비활성(정지)
    case Absconded = 'absconded'; // 이탈 — 소재 불명 (WorkerExit 이 확정하면 여기로 온다)
    case Returned = 'returned';  // 귀국
    case Rejected = 'rejected';  // 가입 거절

    /** 사람이 읽는 한국어 이름 */
    public function label(): string
    {
        return match ($this) {
            self::Pending => '승인 대기',
            self::Active => '재직',
            self::Inactive => '비활성',
            self::Absconded => '이탈',
            self::Returned => '귀국',
            self::Rejected => '가입 거절',
        };
    }

    /** 앱 로그인 가능 여부 — 활성 계정만. */
    public function canLogin(): bool
    {
        return $this === self::Active;
    }

    /** 승인 대기 상태(승인/거절 대상)인지. */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /** 그리드 셀렉트 옵션용 [value,label] 목록. */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
