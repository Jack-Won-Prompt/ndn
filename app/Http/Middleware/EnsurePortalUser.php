<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 관리자 앱 API 접근 제어 (CLAUDE.md §9).
 *
 * 근로자 API(`worker` 미들웨어)와 정확히 반대다. Sanctum 토큰의 소유자가
 * User 이고 관리자 앱 허용 역할(ndn_admin / city_officer / farm_owner)을
 * 가진 경우에만 통과시킨다.
 *
 * 근로자 토큰으로 관리자 API 를 호출하면 403 이다. 두 미들웨어가 서로를
 * 배제하므로 토큰 종류가 뒤섞일 수 없다.
 */
class EnsurePortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, '관리자 토큰이 아닙니다.');
        }

        if (! $user->canUsePortalApp()) {
            abort(403, '관리자 앱을 사용할 수 있는 역할이 아닙니다.');
        }

        return $next($request);
    }
}
