<?php

declare(strict_types=1);

namespace App\Domains\Arrival\Enums;

/**
 * 입국 전 확인해야 하는 서류 체크리스트 (업무흐름 §4·§5).
 *
 * 체크 결과는 arrival_records.documents 에 JSON(키 → bool)으로 저장한다.
 * 서류 **파일**을 여기 두지 않는다 — 여권 사본 등은 온보딩의 private 스토리지에만
 * 존재하고, 여기서는 "확인했는가"만 기록한다 (CLAUDE.md §7-1).
 */
enum ArrivalDocument: string
{
    case Passport = 'passport';            // 여권
    case VisaE8 = 'visa_e8';               // E-8 비자
    case FlightTicket = 'flight_ticket';   // 항공권
    case Contract = 'contract';            // 근로계약서
    case HealthCheck = 'health_check';     // 건강검진 확인서
    case Insurance = 'insurance';          // 보험 가입 확인

    public function label(): string
    {
        return match ($this) {
            self::Passport => '여권',
            self::VisaE8 => 'E-8 비자',
            self::FlightTicket => '항공권',
            self::Contract => '근로계약서',
            self::HealthCheck => '건강검진 확인서',
            self::Insurance => '보험 가입 확인',
        };
    }

    /** 이 서류 없이는 입국을 진행할 수 없는지 (필수 여부) */
    public function isRequired(): bool
    {
        return in_array($this, [self::Passport, self::VisaE8, self::FlightTicket], true);
    }

    /** @return list<string> 모든 체크 키 */
    public static function keys(): array
    {
        return array_map(fn (self $d) => $d->value, self::cases());
    }

    /** 모두 미확인(false)인 기본 체크리스트 */
    public static function emptyChecklist(): array
    {
        return array_fill_keys(self::keys(), false);
    }
}
