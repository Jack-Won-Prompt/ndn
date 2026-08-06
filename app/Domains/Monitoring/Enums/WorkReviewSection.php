<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Enums;

/**
 * 근무상태 종합 점검표의 점검 영역 (원본 §4·§5·§6·§7).
 *
 * 영역이 답안의 눈금을 정한다. 원본은 영역마다 다른 낱말을 쓰지만
 * (양호/보통/미흡 · 우수/양호/개선 필요 · 확인/미확인) 저장값은 셋으로 통일한다 —
 * 낱말이 바뀌어도 지난 점검 기록을 다시 해석할 필요가 없다.
 */
enum WorkReviewSection: string
{
    case Attendance = 'attendance';
    case Performance = 'performance';
    case Community = 'community';
    case Safety = 'safety';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => '근태 및 출·퇴근',
            self::Performance => '근무상태 및 업무능력',
            self::Community => '협동 및 생활관리',
            self::Safety => '안전·보건 및 법정사항',
        };
    }

    /** 3단계 눈금인가, 확인/미확인 2단계인가. */
    public function isRating(): bool
    {
        return $this !== self::Safety;
    }

    /**
     * 화면에 보일 보기와 저장값.
     *
     * @return array<string, string> 저장값 => 라벨
     */
    public function options(): array
    {
        return match ($this) {
            self::Attendance => ['high' => '양호', 'mid' => '보통', 'low' => '미흡'],
            self::Performance => ['high' => '우수', 'mid' => '양호', 'low' => '개선 필요'],
            self::Community => ['high' => '우수', 'mid' => '양호', 'low' => '미흡'],
            self::Safety => ['yes' => '확인', 'no' => '미확인'],
        };
    }

    /** @return list<self> */
    public static function ordered(): array
    {
        return [self::Attendance, self::Performance, self::Community, self::Safety];
    }
}
