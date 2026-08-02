<?php

declare(strict_types=1);

use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;

/**
 * 로그인 상태 유지 — 세션 만료(SESSION_LIFETIME) 후에도 remember 쿠키로 재로그인 없이 접속.
 *
 * 로그인 폼에 체크박스가 없어 remember 가 항상 false 로 넘어가던 회귀를 막는다.
 */
beforeEach(fn () => $this->seed(RoleSeeder::class));

function rememberTestAdmin(): User
{
    $user = User::factory()->create(['password' => 'password']);
    $user->assignRole(UserRole::NdnAdmin->value);

    return $user;
}

it('로그인 유지를 체크하면 remember 쿠키가 발급된다', function () {
    $user = rememberTestAdmin();

    $response = $this->post(route('admin.login.attempt'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('admin.shell'));
    $response->assertCookie(Auth::guard()->getRecallerName());
    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('체크하지 않으면 remember 쿠키가 발급되지 않는다', function () {
    $user = rememberTestAdmin();

    $response = $this->post(route('admin.login.attempt'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.shell'));
    $response->assertCookieMissing(Auth::guard()->getRecallerName());
});

it('협력 포털 로그인에서도 로그인 유지가 동작한다', function () {
    $user = User::factory()->create(['password' => 'password']);
    $user->assignRole(UserRole::CityOfficer->value);

    $response = $this->post(route('portal.login.attempt'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => '1',
    ]);

    $response->assertCookie(Auth::guard()->getRecallerName());
    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('로그인 화면에 로그인 유지 체크박스가 있다', function () {
    $this->get(route('admin.login'))->assertOk()->assertSee('name="remember"', false);
    $this->get(route('portal.login'))->assertOk()->assertSee('name="remember"', false);
});
