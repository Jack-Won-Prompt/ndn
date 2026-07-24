<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * NDN 운영 콘솔(/admin) 접근 제어 (CLAUDE.md §2).
 *
 * 로그인 + ndn_admin 역할을 요구한다. 미로그인 시 로그인 화면으로, 로그인했으나
 * 권한이 없으면 403.
 */
class EnsureNdnAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::user();

        if (! method_exists($user, 'isRole') || ! $user->isRole(UserRole::NdnAdmin)) {
            abort(403, 'NDN 관리자만 접근할 수 있습니다.');
        }

        return $next($request);
    }
}
