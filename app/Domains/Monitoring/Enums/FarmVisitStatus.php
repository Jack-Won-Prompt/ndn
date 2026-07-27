<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Enums;

/**
 * 농가 방문 점검의 상태 평가 (농가 상태·근로자 근무 상태 공통).
 */
enum FarmVisitStatus: string
{
    case Normal = 'normal';    // 정상(양호)
    case Caution = 'caution';  // 주의
    case Issue = 'issue';      // 문제

    public function label(): string
    {
        return match ($this) {
            self::Normal => '정상',
            self::Caution => '주의',
            self::Issue => '문제',
        };
    }

    /** 그리드/폼 셀렉트 옵션 [value,label]. */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
