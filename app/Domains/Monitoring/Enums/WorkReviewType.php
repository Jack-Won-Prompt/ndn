<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Enums;

/** 점검 유형 (근무상태 종합 점검표 §1). */
enum WorkReviewType: string
{
    case Regular = 'regular';
    case Occasional = 'occasional';
    case Special = 'special';
    case Recheck = 'recheck';

    public function label(): string
    {
        return match ($this) {
            self::Regular => '정기점검',
            self::Occasional => '수시점검',
            self::Special => '특별점검',
            self::Recheck => '재점검',
        };
    }
}
