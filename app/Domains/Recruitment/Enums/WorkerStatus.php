<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Enums;

/**
 * 근로자 계정 상태 (CLAUDE.md §5, §7-7).
 *
 * 가입은 관리자 승인제: 셀프 가입 직후 pending → 관리자 승인 시 active.
 * 앱 로그인은 '지금 한국에서 일하는' 상태만 허용한다(canLogin).
 *
 * 뒤쪽 넷(재입국·추방·이직·기간연장)은 현장에서 실제로 갈라 보는 상태다.
 * '재직' 하나로 뭉뚱그리면 재입국자와 처음 온 사람을 구분할 수 없고, 추방과
 * 자진 귀국이 같은 칸에 섞인다 — 지자체 보고에서 이 둘은 전혀 다른 건이다.
 */
enum WorkerStatus: string
{
    case Pending = 'pending';    // 가입 신청 — 승인 대기
    case Active = 'active';      // 재직(활성) — 로그인·업무 가능
    case Inactive = 'inactive';  // 비활성(정지)
    case Absconded = 'absconded'; // 무단이탈 — 소재 불명 (WorkerExit 이 확정하면 여기로 온다)
    case Returned = 'returned';  // 귀국
    case Rejected = 'rejected';  // 가입 거절
    case Reentered = 'reentered'; // 재입국 — 귀국했다가 다시 들어와 일하는 중
    case Deported = 'deported';  // 추방 — 강제 출국. 자진 귀국과 구분해 보고한다
    case Transferred = 'transferred'; // 이직 — 다른 농가로 옮겨 일하는 중
    case Extended = 'extended';  // 기간연장 — 계약 기간을 늘려 계속 일하는 중

    /** 사람이 읽는 한국어 이름 */
    public function label(): string
    {
        return match ($this) {
            self::Pending => '승인 대기',
            self::Active => '재직',
            self::Inactive => '비활성',
            self::Absconded => '무단이탈',
            self::Returned => '귀국',
            self::Rejected => '가입 거절',
            self::Reentered => '재입국',
            self::Deported => '추방',
            self::Transferred => '이직',
            self::Extended => '기간연장',
        };
    }

    /**
     * 지금 한국에서 일하고 있는 상태인지.
     *
     * 재입국·이직·기간연장은 이름만 다를 뿐 모두 '일하는 중'이다. 이걸 갈라 두지
     * 않으면 기간을 연장한 사람이 앱에 로그인하지 못하고, 배정 후보에서도 빠진다.
     */
    public function isWorking(): bool
    {
        return in_array($this, [
            self::Active,
            self::Reentered,
            self::Transferred,
            self::Extended,
        ], true);
    }

    /** 앱 로그인 가능 여부 — 지금 일하고 있는 계정만. */
    public function canLogin(): bool
    {
        return $this->isWorking();
    }

    /** 승인 대기 상태(승인/거절 대상)인지. */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * 일하는 중인 상태들 — 쿼리에 넣을 값 목록.
     *
     * @return list<string>
     */
    public static function workingValues(): array
    {
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->isWorking()),
        ));
    }

    /** 그리드 셀렉트 옵션용 [value,label] 목록. */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
