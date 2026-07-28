<?php

declare(strict_types=1);

use App\Domains\Support\Events\AdminAlertBroadcast;
use App\Domains\Support\Models\AccountDeletionRequest;
use App\Http\Controllers\Admin\ConsoleController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

/**
 * 계정·데이터 삭제 요청 (Google Play 데이터 삭제 정책, §7-3·§7-7).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('개인정보처리방침·이용약관·계정삭제 페이지가 공개(비로그인)로 열린다', function () {
    $this->get('/privacy')->assertOk()->assertSee('개인정보처리방침');
    $this->get('/terms')->assertOk()->assertSee('이용약관');
    $this->get('/account-deletion')->assertOk()->assertSee('계정 및 데이터 삭제 요청');
});

it('삭제 요청을 접수하면 대기 상태로 저장되고 관리자 알림이 발송된다(개인정보 없음)', function () {
    Event::fake([AdminAlertBroadcast::class]);

    $this->post('/account-deletion', [
        'name' => '홍길동',
        'email' => 'gildong@example.com',
        'reason' => '서비스 미사용',
        'confirm' => '1',
    ])->assertRedirect(route('legal.account-deletion'))->assertSessionHas('deletion_ok');

    $req = AccountDeletionRequest::first();
    expect($req)->not->toBeNull();
    expect($req->status)->toBe(AccountDeletionRequest::STATUS_PENDING);
    expect($req->email)->toBe('gildong@example.com');

    Event::assertDispatched(AdminAlertBroadcast::class, function ($e) {
        return $e->kind === 'account_deletion'
            && $e->screen === 'account-deletions'
            && ! preg_match('/@|[0-9]{6}|01[0-9]/', $e->message);   // 개인정보 패턴 없음
    });
});

it('동의 체크 없이는 접수되지 않는다', function () {
    $this->post('/account-deletion', [
        'name' => '홍길동', 'email' => 'g@example.com',
    ])->assertSessionHasErrors('confirm');

    expect(AccountDeletionRequest::count())->toBe(0);
});

it('관리자만 요청을 처리(완료/거절)할 수 있다', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);
    $req = AccountDeletionRequest::create([
        'name' => '홍길동', 'email' => 'g@example.com', 'status' => 'pending',
    ]);

    actingAs($admin)
        ->post(route('admin.account-deletions.process', $req->id), ['status' => 'completed'])
        ->assertOk()->assertJson(['ok' => true]);

    $req->refresh();
    expect($req->status)->toBe(AccountDeletionRequest::STATUS_COMPLETED);
    expect($req->processed_at)->not->toBeNull();
    expect($req->processed_by)->toBe($admin->id);
});

it('배지 카운트에 대기 중인 삭제 요청 수가 반영된다', function () {
    AccountDeletionRequest::create(['name' => 'A', 'email' => 'a@example.com', 'status' => 'pending']);
    AccountDeletionRequest::create(['name' => 'B', 'email' => 'b@example.com', 'status' => 'completed']);

    expect(ConsoleController::badgeCounts()['account-deletions'])->toBe(1);
})->group('guard');
