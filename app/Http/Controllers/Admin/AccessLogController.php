<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;

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
                'at' => $l->created_at?->format('Y-m-d H:i:s'),
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

    /** 요약 카운트(전체·게스트·로그인·오늘). */
    public static function summary(): array
    {
        return [
            'total' => AccessLog::count(),
            'guest' => AccessLog::whereNull('user_id')->count(),
            'auth' => AccessLog::whereNotNull('user_id')->count(),
            'today' => AccessLog::whereDate('created_at', now()->toDateString())->count(),
        ];
    }
}
