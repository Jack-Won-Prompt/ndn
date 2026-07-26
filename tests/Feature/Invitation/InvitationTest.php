<?php

declare(strict_types=1);

use App\Actions\AcceptInvitationAction;
use App\Actions\SendInvitationAction;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Shared\Enums\InvitationStatus;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * 조직 초대(초대 전용 가입) — 발송·수락 Action + 공개 수락 흐름 (CLAUDE.md §7-3).
 */

// 역할 부여(assignRole)를 위해 역할을 시딩한다.
beforeEach(fn () => $this->seed(RoleSeeder::class));

it('초대를 발송하면 대기 상태로 생성되고 토큰은 해시로만 저장된다', function () {
    Notification::fake();
    $admin = User::factory()->create();

    $result = app(SendInvitationAction::class)->execute('invitee@example.com', UserRole::FarmOwner, $admin);

    $inv = $result['invitation'];
    expect($inv->status())->toBe(InvitationStatus::Pending);
    expect($result['token'])->toHaveLength(40);
    // 저장된 토큰은 평문이 아니라 sha256 hex(64자)
    expect($inv->token)->toHaveLength(64)->not->toBe($result['token']);
    expect($inv->token)->toBe(Invitation::hashToken($result['token']));

    Notification::assertSentOnDemand(InvitationNotification::class);
});

it('이미 가입된 이메일로는 초대할 수 없다', function () {
    $admin = User::factory()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    expect(fn () => app(SendInvitationAction::class)->execute('taken@example.com', UserRole::CityOfficer, $admin))
        ->toThrow(ValidationException::class);
});

it('근로자·관리자 역할은 초대할 수 없다', function (UserRole $role) {
    $admin = User::factory()->create();

    expect(fn () => app(SendInvitationAction::class)->execute('x@example.com', $role, $admin))
        ->toThrow(ValidationException::class);
})->with([UserRole::Worker, UserRole::NdnAdmin]);

it('같은 이메일로 재발송하면 이전 대기 초대는 철회된다', function () {
    Notification::fake();
    $admin = User::factory()->create();

    $first = app(SendInvitationAction::class)->execute('dup@example.com', UserRole::FarmOwner, $admin)['invitation'];
    app(SendInvitationAction::class)->execute('dup@example.com', UserRole::FarmOwner, $admin);

    expect($first->fresh()->status())->toBe(InvitationStatus::Revoked);
});

it('유효한 초대를 수락하면 역할이 부여된 계정이 생성된다', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $token = app(SendInvitationAction::class)->execute('newcity@example.com', UserRole::CityOfficer, $admin)['token'];

    $out = app(AcceptInvitationAction::class)->execute($token, '시청담당', 'password123');

    expect($out['user']->email)->toBe('newcity@example.com');
    expect($out['user']->hasRole(UserRole::CityOfficer->value))->toBeTrue();
    expect($out['invitation']->fresh()->status())->toBe(InvitationStatus::Accepted);
});

it('잘못된·철회된 토큰은 수락할 수 없다', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $sent = app(SendInvitationAction::class)->execute('rev@example.com', UserRole::FarmOwner, $admin);
    $sent['invitation']->forceFill(['revoked_at' => now()])->save();

    expect(fn () => app(AcceptInvitationAction::class)->execute($sent['token'], 'x', 'password123'))
        ->toThrow(ValidationException::class);
    expect(fn () => app(AcceptInvitationAction::class)->execute('nope', 'x', 'password123'))
        ->toThrow(ValidationException::class);
});

it('수락한 초대는 재사용할 수 없다', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $token = app(SendInvitationAction::class)->execute('once@example.com', UserRole::SendingAgency, $admin)['token'];
    app(AcceptInvitationAction::class)->execute($token, '송출', 'password123');

    expect(fn () => app(AcceptInvitationAction::class)->execute($token, '송출2', 'password123'))
        ->toThrow(ValidationException::class);
});

it('만료된 초대는 수락할 수 없다', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $sent = app(SendInvitationAction::class)->execute('exp@example.com', UserRole::FarmOwner, $admin);
    $sent['invitation']->forceFill(['expires_at' => now()->subDay()])->save();

    expect($sent['invitation']->fresh()->status())->toBe(InvitationStatus::Expired);
    expect(fn () => app(AcceptInvitationAction::class)->execute($sent['token'], 'x', 'password123'))
        ->toThrow(ValidationException::class);
});

it('공개 수락 페이지 — 유효한 토큰은 폼을, 잘못된 토큰은 안내를 보여준다', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $token = app(SendInvitationAction::class)->execute('page@example.com', UserRole::FarmOwner, $admin)['token'];

    $this->get('/invite/'.$token)->assertOk()->assertSee('계정 설정');
    $this->get('/invite/invalidtoken')->assertOk()->assertSee('유효하지 않은 초대');
});

it('공개 수락 POST 로 계정을 만들 수 있다', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $token = app(SendInvitationAction::class)->execute('poster@example.com', UserRole::FarmOwner, $admin)['token'];

    $this->post('/invite/'.$token, [
        'name' => '포스터',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('portal.login'));

    $user = User::where('email', 'poster@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(UserRole::FarmOwner->value))->toBeTrue();
});
