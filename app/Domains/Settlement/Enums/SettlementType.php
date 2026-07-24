<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Enums;

/**
 * 정착 서비스 종류 (CLAUDE.md §5: bank/insurance/telecom/usim).
 */
enum SettlementType: string
{
    case Bank = 'bank';       // 통장 개설
    case Insurance = 'insurance';  // 보험
    case Telecom = 'telecom';    // 통신
    case Usim = 'usim';       // 유심

    public function label(): string
    {
        return match ($this) {
            self::Bank => '통장',
            self::Insurance => '보험',
            self::Telecom => '통신',
            self::Usim => '유심',
        };
    }
}
