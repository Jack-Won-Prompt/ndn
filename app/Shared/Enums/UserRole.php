<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * 시스템 사용자 역할 6종 (CLAUDE.md §1).
 *
 * spatie/laravel-permission 의 Role 이름과 1:1 매핑된다.
 * 문자열 리터럴로 역할을 비교하지 말고 항상 이 Enum 을 쓸 것.
 */
enum UserRole: string
{
    case CityOfficer = 'city_officer';    // 시청 담당자
    case FarmOwner = 'farm_owner';      // 농가
    case SendingAgency = 'sending_agency';  // 송출기관
    case Worker = 'worker';          // 근로자 (모바일 앱)
    case NdnAdmin = 'ndn_admin';       // NDN 운영자
    case PartnerAgency = 'partner_agency';  // 제휴 대리점 (보험·통신)

    /** 사람이 읽는 한국어 이름 */
    public function label(): string
    {
        return match ($this) {
            self::CityOfficer => '시청 담당자',
            self::FarmOwner => '농가',
            self::SendingAgency => '송출기관',
            self::Worker => '근로자',
            self::NdnAdmin => 'NDN 관리자',
            self::PartnerAgency => '제휴 대리점',
        };
    }

    /** 2FA 필수 역할 (CLAUDE.md §2) */
    public function requiresTwoFactor(): bool
    {
        return in_array($this, [self::NdnAdmin, self::PartnerAgency], true);
    }

    /** 웹 포털이 아닌 API(앱)로만 접속하는 역할 */
    public function usesApiOnly(): bool
    {
        return $this === self::Worker;
    }

    /** @return list<string> 전체 역할 값 목록 (시더용) */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
