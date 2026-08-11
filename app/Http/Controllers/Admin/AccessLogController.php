<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Shared\Support\IpCountry;
use App\Shared\Support\LocalTime;
use Carbon\CarbonInterface;

/**
 * 접속·페이지 접근 로그 조회 (콘솔).
 *
 * 시각은 **보는 사람의 지역 시간**으로 바꿔 보여 준다(§11). 그래서 한국에서 보는
 * 값과 해외에서 보는 값이 다르다 — 화면에 기준 타임존을 적어 두고, 상대 시간을
 * 함께 보여 어긋남이 바로 눈에 띄게 한다.
 */
class AccessLogController extends Controller
{
    /** 최근 접근 로그 (최신순). */
    public static function rows(int $limit = 1000): array
    {
        $now = now();

        return AccessLog::latest('id')->limit($limit)->get()
            ->map(fn (AccessLog $l) => [
                'id' => $l->id,
                'at' => LocalTime::format($l->created_at, 'Y-m-d H:i:s'),
                'ago' => self::ago($l->created_at, $now),
                'actor' => $l->actor ?? '게스트',
                'email' => $l->actor_email,
                'is_guest' => $l->user_id === null,
                'method' => $l->method,
                'path' => $l->path,
                'route' => $l->route_name,
                'status' => $l->status,
                'ip' => $l->ip,
                'country' => $l->country,
                'country_label' => IpCountry::label($l->country),
                // 해외에서 들어온 관리자 접속은 눈에 띄어야 한다.
                'is_foreign' => $l->country !== null
                    && $l->country !== IpCountry::LOCAL
                    && $l->country !== 'KR',
            ])->all();
    }

    /** 표시 기준 타임존 — 화면 머리에 적는다. */
    public static function displayTz(): string
    {
        return LocalTime::tz();
    }

    /** 판별표가 깔려 있는가 — 없으면 국가가 '미상'으로만 나온다. */
    public static function hasGeoData(): bool
    {
        return IpCountry::hasData();
    }

    /** 요약 카운트(전체·오늘·로그인·게스트·해외). '오늘'은 표시 타임존 기준. */
    public static function summary(): array
    {
        $tz = LocalTime::tz();
        // 표시 타임존의 하루 경계를 UTC 범위로 변환해 조회 (created_at 은 UTC 저장)
        $start = now($tz)->startOfDay()->utc();
        $end = now($tz)->endOfDay()->utc();

        return [
            'total' => AccessLog::count(),
            'guest' => AccessLog::whereNull('user_id')->count(),
            'auth' => AccessLog::whereNotNull('user_id')->count(),
            'today' => AccessLog::whereBetween('created_at', [$start, $end])->count(),
            'foreign' => AccessLog::whereNotNull('country')
                ->whereNotIn('country', [IpCountry::LOCAL, 'KR'])
                ->count(),
        ];
    }

    /**
     * 국가별 건수 (많은 순). 판별된 것만 센다.
     *
     * @return list<array{code: string, label: string, count: int, foreign: bool}>
     */
    public static function byCountry(int $limit = 12): array
    {
        return AccessLog::query()
            ->whereNotNull('country')
            ->selectRaw('country, count(*) as aggregate_count')
            ->groupBy('country')
            ->orderByDesc('aggregate_count')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'code' => (string) $r->country,
                'label' => IpCountry::label((string) $r->country),
                'count' => (int) $r->aggregate_count,
                'foreign' => $r->country !== IpCountry::LOCAL && $r->country !== 'KR',
            ])
            ->all();
    }

    /**
     * '3분 전' 처럼. 시각이 어긋나면 여기서 바로 드러난다 —
     * 방금 한 일이 '9시간 전'으로 보이면 설정이 잘못된 것이다.
     */
    private static function ago(?CarbonInterface $at, CarbonInterface $now): string
    {
        if ($at === null) {
            return '—';
        }

        $seconds = (int) $at->diffInSeconds($now, false);

        // 미래로 나오면 저장·표시 타임존이 어긋난 것이다. 숨기지 않고 드러낸다.
        if ($seconds < -60) {
            return '시각 이상(미래)';
        }
        if ($seconds < 60) {
            return '방금';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).'분 전';
        }
        if ($seconds < 86400) {
            return intdiv($seconds, 3600).'시간 전';
        }

        return intdiv($seconds, 86400).'일 전';
    }
}
