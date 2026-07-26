<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AccessLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 접속·페이지 접근 로그 기록 (메인 비로그인 + 로그인 이후 페이지 모두).
 *
 * 페이지 GET 요청만 기록한다. 에셋(Apache 직접 서빙)·AJAX/JSON 폴링·비 GET 은 제외해
 * 실제 페이지 이동만 남긴다. 응답 종료 후(terminate) 기록하므로 응답 속도에 영향 없다.
 */
class RecordAccessLog
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->shouldSkip($request)) {
            return;
        }

        try {
            $user = $request->user();
            if ($user) {
                // preventLazyLoading(§11) 하에서 안전하게 역할 조회
                $role = $user->relationLoaded('roles')
                    ? $user->getRoleNames()->first()
                    : $user->roles()->pluck('name')->first();
                $actor = $user->name.' ('.($role ?? 'user').')';
            } else {
                $actor = '게스트';
            }

            AccessLog::create([
                'user_id' => $user?->id,
                'actor' => $actor,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'route_name' => $request->route()?->getName(),
                'status' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'referer' => mb_substr((string) $request->headers->get('referer'), 0, 500) ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // 로깅 실패가 요청을 깨뜨리지 않도록 흡수
            Log::warning('[AccessLog] 기록 실패: '.$e->getMessage());
        }
    }

    /** 페이지 접근이 아닌 요청은 건너뛴다. */
    private function shouldSkip(Request $request): bool
    {
        if ($request->method() !== 'GET') {
            return true;
        }
        // AJAX/JSON 폴링(채팅·그리드 등)은 페이지 접근이 아니다
        if ($request->ajax() || $request->wantsJson()) {
            return true;
        }
        // 방송 인증·헬스체크 등 비페이지 경로
        $path = $request->path();
        foreach (['broadcasting/auth', 'up', 'livewire'] as $skip) {
            if (str_starts_with($path, $skip)) {
                return true;
            }
        }

        return false;
    }
}
