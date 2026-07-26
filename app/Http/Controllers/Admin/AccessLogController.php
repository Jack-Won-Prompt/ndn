<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Shared\Support\LocalTime;

/**
 * 접속·페이지 접근 로그 조회 (콘솔).
 */
class AccessLogController extends Controller
{
    /** 최근 접근 로그 (최신순). */
    public static function rows(int $limit = 1000): array
    {
        return AccessLog::latest('id')->limit($limit)->get()
            ->map(fn (AccessLog $l) => [
                'id' => $l->id,
                'at' => LocalTime::format($l->created_at, 'Y-m-d H:i:s'),
                'actor' => $l->actor ?? '게스트',
                'is_guest' => $l->user_id === null,
                'method' => $l->method,
                'path' => $l->path,
                'route' => $l->route_name,
                'status' => $l->status,
                'ip' => $l->ip,
                'agent' => $l->user_agent,
            ])->all();
    }

    /** 요약 카운트(전체·게스트·로그인·오늘). '오늘'은 표시 타임존(KST) 기준. */
    public static function summary(): array
    {
        $tz = LocalTime::tz();
        // KST 하루 경계를 UTC 범위로 변환해 조회 (created_at 은 UTC 저장)
        $start = now($tz)->startOfDay()->utc();
        $end = now($tz)->endOfDay()->utc();

        return [
            'total' => AccessLog::count(),
            'guest' => AccessLog::whereNull('user_id')->count(),
            'auth' => AccessLog::whereNotNull('user_id')->count(),
            'today' => AccessLog::whereBetween('created_at', [$start, $end])->count(),
        ];
    }
}
