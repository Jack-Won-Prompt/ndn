<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Carbon\CarbonInterface;

/**
 * 화면 표시용 로컬 시각 변환 (CLAUDE.md §11).
 *
 * 저장은 UTC, 표시는 "접속한 국가의 시간"(뷰어 타임존)으로 변환한다.
 * 뷰어 타임존은 브라우저가 심어주는 ndn_tz 쿠키(웹) 또는 X-Timezone 헤더(앱 API)에서
 * 읽고, 없으면 config('ndn.timezone')(기본 Asia/Seoul)로 폴백한다.
 * 한국·해외에서 동시에 써도 각자 자기 지역 시간으로 보인다.
 */
class LocalTime
{
    /** 뷰어(접속자)의 타임존. 쿠키/헤더 → 없으면 기본 Asia/Seoul. */
    public static function tz(): string
    {
        $request = request();
        $candidate = $request?->cookie('ndn_tz') ?: $request?->headers->get('X-Timezone');

        if (is_string($candidate) && $candidate !== '' && self::isValidTz($candidate)) {
            return $candidate;
        }

        return (string) config('ndn.timezone', 'Asia/Seoul');
    }

    private static function isValidTz(string $tz): bool
    {
        static $all = null;
        $all ??= timezone_identifiers_list();

        return in_array($tz, $all, true);
    }

    /** UTC 저장 시각을 표시 타임존으로 변환해 포맷. null 이면 null. */
    public static function format(?CarbonInterface $date, string $format = 'Y-m-d H:i'): ?string
    {
        return $date?->copy()->setTimezone(self::tz())->format($format);
    }
}
