<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Support\Services\ChatService;
use App\Shared\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 시청·농가·해외협력사(제휴사) 포털 — 로그인 후 채팅 등 협업 기능.
 * NDN 관리자는 /admin 콘솔, 근로자는 앱(API) 사용.
 */
class PortalController extends Controller
{
    /** 포털 접근 허용 역할 */
    private const ROLES = [
        UserRole::CityOfficer->value,
        UserRole::FarmOwner->value,
        UserRole::SendingAgency->value,
        UserRole::PartnerAgency->value,
    ];

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check() && $this->isPortalUser()) {
            return redirect()->route('portal.index');
        }

        return view('portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $cred = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($cred, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => '이메일 또는 비밀번호가 올바르지 않습니다.']);
        }

        if (! $this->isPortalUser()) {
            Auth::logout();

            throw ValidationException::withMessages(['email' => '포털 접근 권한이 없는 계정입니다.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('portal.index'));
    }

    public function index(): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('portal.login');
        }
        $user = Auth::user();
        if ($user->isRole(UserRole::NdnAdmin)) {
            return redirect()->to(url('admin'));   // 관리자는 콘솔로
        }
        if (! $this->isPortalUser()) {
            Auth::logout();

            return redirect()->route('portal.login');
        }

        return view('portal.index', [
            'user' => $user,
            'me' => app(ChatService::class)->partyForUser($user),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    private function isPortalUser(): bool
    {
        return Auth::user()?->hasAnyRole(self::ROLES) ?? false;
    }
}
