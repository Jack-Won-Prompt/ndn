<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

/**
 * 민원 유형 (CLAUDE.md §5, 업무흐름 §8).
 * 근로자 앱 원터치 버튼: 문제 신고 / 문의 / 기간 연장 / 조기 귀국.
 */
enum TicketType: string
{
    case Report = 'report';         // 문제 신고
    case Inquiry = 'inquiry';        // 문의
    case ExtendStay = 'extend_stay';    // 기간 연장 신청
    case EarlyReturn = 'early_return';   // 조기 귀국 신청

    public function label(): string
    {
        return match ($this) {
            self::Report => '문제 신고',
            self::Inquiry => '문의',
            self::ExtendStay => '기간 연장',
            self::EarlyReturn => '조기 귀국',
        };
    }
}
