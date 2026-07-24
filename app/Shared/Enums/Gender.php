<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * 성별 선호 (DemandRequest 등에서 사용).
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Any = 'any';   // 무관

    public function label(): string
    {
        return match ($this) {
            self::Male => '남성',
            self::Female => '여성',
            self::Any => '무관',
        };
    }
}
