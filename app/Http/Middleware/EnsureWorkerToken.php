<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Recruitment\Models\Worker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 근로자 앱 API 스코프 강제 (CLAUDE.md §9).
 *
 * Sanctum 토큰의 소유자(tokenable)가 Worker 인지 확인한다. Worker 가 아니면 403.
 * 모든 API 엔드포인트는 URL 에 worker_id 를 받지 않고 인증된 Worker 본인에서
 * 리소스를 파생시키므로, 이 미들웨어 통과만으로 "자기 자신의 리소스만 접근"이 보장된다.
 */
class EnsureWorkerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Worker) {
            abort(403, '근로자 토큰이 아닙니다.');
        }

        return $next($request);
    }
}
