<?php

declare(strict_types=1);

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Actions\SendNoticeAction;
use App\Domains\Support\Models\Notice;
use App\Domains\Support\Notifications\NoticeNotification;
use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

/**
 * 근로자 공지사항 발송 (FCM 푸시 + 인앱) — CLAUDE.md §6·§7-3·§8.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('전체 공지는 재직 근로자 전원에게 발송되고 발송 수가 기록된다', function () {
    Notification::fake();
    Worker::factory()->count(3)->create(['status' => WorkerStatus::Active, 'locale' => 'ko']);
    Worker::factory()->create(['status' => WorkerStatus::Pending, 'locale' => 'ko']); // 대상 아님

    $notice = app(SendNoticeAction::class)->execute('안전 교육 안내', '이번 주 안전 교육이 있습니다.', Notice::TARGET_ALL, null, 1);

    expect($notice->recipients_count)->toBe(3);
    Notification::assertSentTimes(NoticeNotification::class, 3);
});

it('국적별 공지는 해당 국적 재직 근로자에게만 발송된다', function () {
    Notification::fake();
    Worker::factory()->count(2)->create(['status' => WorkerStatus::Active, 'nationality' => 'BD', 'locale' => 'ko']);
    Worker::factory()->create(['status' => WorkerStatus::Active, 'nationality' => 'VN', 'locale' => 'ko']);

    $notice = app(SendNoticeAction::class)->execute('제목', '내용', Notice::TARGET_NATIONALITY, 'BD', 1);

    expect($notice->recipients_count)->toBe(2);
    Notification::assertSentTimes(NoticeNotification::class, 2);
});

it('본문에 개인정보가 있으면 발송이 거부되고 공지가 생성되지 않는다 (§7-3)', function () {
    Notification::fake();
    Worker::factory()->create(['status' => WorkerStatus::Active, 'locale' => 'ko']);

    expect(fn () => app(SendNoticeAction::class)->execute('제목', '연락처 010-1234-5678 로 문의', Notice::TARGET_ALL, null, 1))
        ->toThrow(ValidationException::class);

    expect(Notice::count())->toBe(0);
    Notification::assertNothingSent();
});

it('공지 알림은 FCM+인앱 채널이며 개인정보 프리 인터페이스를 구현한다', function () {
    $n = new NoticeNotification(1, '공지 제목', '공지 내용입니다.');
    expect($n)->toBeInstanceOf(PersonalDataFreeChannel::class);
    expect($n->via(new stdClass))->toBe(['database', 'fcm']);
    expect($n->toFcm(new stdClass)['data']['screen'])->toBe('notices');
});

it('근로자 API 는 본인이 대상인 공지만 반환한다', function () {
    $bd = Worker::factory()->create(['status' => WorkerStatus::Active, 'nationality' => 'BD', 'locale' => 'ko']);

    Notice::create(['title' => '전체 공지', 'body' => 'x', 'target' => 'all', 'recipients_count' => 1]);
    Notice::create(['title' => '방글라 공지', 'body' => 'y', 'target' => 'nationality', 'target_value' => 'BD', 'recipients_count' => 1]);
    Notice::create(['title' => '베트남 공지', 'body' => 'z', 'target' => 'nationality', 'target_value' => 'VN', 'recipients_count' => 1]);

    Sanctum::actingAs($bd);
    $titles = collect($this->getJson('/api/v1/notices')->assertOk()->json('data'))->pluck('title');

    expect($titles)->toContain('전체 공지')->toContain('방글라 공지');
    expect($titles)->not->toContain('베트남 공지');
});
