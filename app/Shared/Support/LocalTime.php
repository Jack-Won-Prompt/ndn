<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Carbon\CarbonInterface;

/**
 * 화면 표시용 로컬 시각 변환 (CLAUDE.md §11).
 *
 * 저장은 UTC, 표시는 config('ndn.timezone')(기본 Asia/Seoul)로 변환한다.
 * 날짜·시각을 사용자에게 보여주는 모든 지점에서 이 헬퍼를 쓴다.
 */
class LocalTime
{
    public static function tz(): string
    {
        return (string) config('ndn.timezone', 'Asia/Seoul');
    }

    /** UTC 저장 시각을 표시 타임존으로 변환해 포맷. null 이면 null. */
    public static function format(?CarbonInterface $date, string $format = 'Y-m-d H:i'): ?string
    {
        return $date?->copy()->setTimezone(self::tz())->format($format);
    }
}
