<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

/**
 * 이 애플리케이션은 운영 콘솔 전용 커스텀 로그인(admin.login)을 사용한다.
 * Fortify 패키지가 자동 등록하는 기본 뷰 라우트(/login, /register, ...)는
 * 뷰 응답이 바인딩되어 있지 않아 그대로 두면 BindingResolutionException 이
 * 발생한다. 여기서 모든 Fortify 뷰를 관리자 로그인으로 리다이렉트해
 * 500 오류 없이 안전하게 흡수한다.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $toAdminLogin = fn () => redirect()->route('admin.login');

        Fortify::loginView($toAdminLogin);
        Fortify::registerView($toAdminLogin);
        Fortify::requestPasswordResetLinkView($toAdminLogin);
        Fortify::resetPasswordView($toAdminLogin);
        Fortify::verifyEmailView($toAdminLogin);
        Fortify::confirmPasswordView($toAdminLogin);
        Fortify::twoFactorChallengeView($toAdminLogin);
    }
}
