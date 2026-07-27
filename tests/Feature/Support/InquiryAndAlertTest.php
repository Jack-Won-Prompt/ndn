<?php

declare(strict_types=1);

use App\Domains\Recruitment\Actions\RegisterWorkerAction;
use App\Domains\Support\Events\AdminAlertBroadcast;
use App\Domains\Support\Models\ChatVisitor;
use App\Domains\Support\Services\ChatService;
use App\Http\Controllers\Admin\ConsoleController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

/**
 * 홈페이지 문의(채팅 분리) + 관리자 실시간 알림(Pusher) (CLAUDE.md §7-3, §8).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
});

/** 방문자 문의 대화 하나를 만든다(방문자 첫 메시지). */
function makeVisitorInquiry(string $body = '신청 절차가 궁금합니다', string $locale = 'ko'): void
{
    $chat = app(ChatService::class);
    $visitor = ChatVisitor::create(['token' => Str::random(48), 'locale' => $locale, 'last_seen_at' => now()]);
    $me = $chat->partyForVisitor($visitor);
    $conv = $chat->resolveConversation($me, ['ndn', null, 'ko']);
    $chat->send($conv, $me, $body);
}

it('방문자 문의는 문의 목록에 보이고 채팅 목록에서는 제외된다', function () {
    makeVisitorInquiry();

    actingAs($this->admin);

    $inq = $this->getJson(route('admin.inquiries.conversations'))->assertOk()->json('conversations');
    expect($inq)->toHaveCount(1);
    expect($inq[0]['other_type'])->toBe('visitor');

    $chat = $this->getJson(route('chat.conversations'))->assertOk()->json('conversations');
    expect(collect($chat)->pluck('other_type'))->not->toContain('visitor');
});

it('안 읽은 문의 수(배지 카운트)가 정확하고 열람하면 0이 된다', function () {
    makeVisitorInquiry();
    $chat = app(ChatService::class);

    expect($chat->unreadInquiryCount())->toBe(1);

    actingAs($this->admin);
    // 대화 열람(메시지 조회) → 읽음 처리
    $convId = $this->getJson(route('admin.inquiries.conversations'))->json('conversations.0.id');
    $this->getJson(route('admin.inquiries.messages', $convId))->assertOk();

    expect($chat->unreadInquiryCount())->toBe(0);
});

it('새 홈페이지 문의가 오면 관리자 실시간 알림이 발송된다 (개인정보 없음)', function () {
    Event::fake([AdminAlertBroadcast::class]);

    $this->postJson(route('site.chat.message'), ['body' => '베트남에서 문의드립니다'])->assertOk();

    Event::assertDispatched(AdminAlertBroadcast::class, function ($e) {
        return $e->kind === 'inquiry'
            && $e->screen === 'inquiries'
            && ! preg_match('/[0-9]{6}|@|01[0-9]/', $e->message);   // 개인정보 패턴 없음
    });
});

it('근로자 셀프 가입 시 관리자 실시간 알림이 발송된다', function () {
    Event::fake([AdminAlertBroadcast::class]);

    app(RegisterWorkerAction::class)->execute([
        'name' => '김근로', 'email' => 'w'.Str::random(5).'@ex.com', 'password' => 'secret123',
        'nationality' => 'vn', 'locale' => 'vi', 'passport_no' => 'M'.random_int(1000000, 9999999),
    ]);

    Event::assertDispatched(AdminAlertBroadcast::class, fn ($e) => $e->kind === 'signup' && $e->screen === 'signups');
});

it('배지 카운트는 미읽음 문의와 가입 대기 건수를 반영한다', function () {
    makeVisitorInquiry();
    app(RegisterWorkerAction::class)->execute([
        'name' => '박근로', 'email' => 'p'.Str::random(5).'@ex.com', 'password' => 'secret123',
        'nationality' => 'bd', 'locale' => 'bn', 'passport_no' => 'M'.random_int(1000000, 9999999),
    ]);

    $badges = ConsoleController::badgeCounts();

    expect($badges['inquiries'])->toBe(1);
    expect($badges['signups'])->toBeGreaterThanOrEqual(1);
})->group('guard');
