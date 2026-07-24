<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Shared\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * NDN 운영 콘솔 인증 (세션 기반). ndn_admin 역할만 로그인 허용.
 */
class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.shell');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => '이메일 또는 비밀번호가 올바르지 않습니다.',
            ]);
        }

        $user = Auth::user();

        // ndn_admin 이 아니면 즉시 로그아웃
        if (! $user->isRole(UserRole::NdnAdmin)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'NDN 관리자 계정만 접근할 수 있습니다.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.shell'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
