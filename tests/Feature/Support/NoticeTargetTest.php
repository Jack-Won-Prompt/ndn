<?php

declare(strict_types=1);

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\Notice;
use App\Domains\Support\Notifications\NoticeNotification;
use App\Models\User;
use App\Shared\Enums\UserRole;
use App\Shared\Notifications\Models\DeviceToken;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

/**
 * 공지 대상 다섯 가지 (업무흐름 §8).
 *
 * 전체 / 근로자 전체 / 국적별 / 상태별 / 근로자 선택.
 * 잘못 가면 되돌릴 수 없는 기능이라 **누가 받고 누가 안 받는지**를 본다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);

    // 한국어로 고정한다. 다른 언어면 앱 목록이 번역돼 나와 제목 비교가 흔들린다
    // (번역기 호출까지 붙어 테스트가 느려진다).
    $this->vn = Worker::factory()->create(['nationality' => 'VN', 'locale' => 'ko', 'status' => WorkerStatus::Active->value]);
    $this->bd = Worker::factory()->create(['nationality' => 'BD', 'locale' => 'ko', 'status' => WorkerStatus::Active->value]);
    $this->gone = Worker::factory()->create(['nationality' => 'VN', 'locale' => 'ko', 'status' => WorkerStatus::Returned->value]);
});

function sendNotice(array $override = []): TestResponse
{
    return actingAs(test()->admin)->post(route('admin.notices.store'), array_merge([
        'title' => '안전 교육 안내',
        'body' => '이번 주 안전 교육이 있습니다.',
        'target' => Notice::TARGET_ALL,
    ], $override));
}

it('근로자 전체 공지는 재직자에게만 간다', function () {
    sendNotice()->assertRedirect();

    Notification::assertSentTo([$this->vn, $this->bd], NoticeNotification::class);
    // 귀국한 사람에게 공지가 가면 안 된다.
    Notification::assertNotSentTo($this->gone, NoticeNotification::class);

    expect(Notice::firstOrFail()->recipients_count)->toBe(2);
});

it('전체 공지는 앱을 쓰는 담당자에게도 간다', function () {
    // 기기를 등록한 담당자만 센다 — 앱을 안 쓰는 사람까지 세면 이력이 거짓말을 한다.
    $appUser = User::factory()->create();
    $appUser->assignRole(UserRole::NdnAdmin->value);
    DeviceToken::factory()->create([
        'tokenable_type' => $appUser->getMorphClass(),
        'tokenable_id' => $appUser->id,
    ]);

    $noApp = User::factory()->create();

    sendNotice(['target' => Notice::TARGET_EVERYONE])->assertRedirect();

    Notification::assertSentTo([$this->vn, $this->bd], NoticeNotification::class);
    Notification::assertSentTo($appUser, NoticeNotification::class);
    Notification::assertNotSentTo($noApp, NoticeNotification::class);

    // 근로자 2 + 담당자 1
    expect(Notice::firstOrFail()->recipients_count)->toBe(3);
});

it('담당자는 한국어 원문 그대로 받는다', function () {
    // 번역해 보내면 원문과 달라져 "무슨 공지를 보냈나" 를 되짚을 때 헷갈린다.
    $appUser = User::factory()->create();
    DeviceToken::factory()->create([
        'tokenable_type' => $appUser->getMorphClass(),
        'tokenable_id' => $appUser->id,
    ]);

    sendNotice(['target' => Notice::TARGET_EVERYONE])->assertRedirect();

    Notification::assertSentTo($appUser, NoticeNotification::class,
        fn (NoticeNotification $n) => $n->noticeTitle === '안전 교육 안내');
});

it('고른 근로자에게만 가고 누구에게 갔는지 남는다', function () {
    sendNotice([
        'target' => Notice::TARGET_SELECTED,
        'worker_ids' => [$this->vn->id],
    ])->assertRedirect();

    Notification::assertSentTo($this->vn, NoticeNotification::class);
    Notification::assertNotSentTo($this->bd, NoticeNotification::class);

    $notice = Notice::firstOrFail();

    expect($notice->recipients_count)->toBe(1)
        // "왜 이 사람만 받았나" 는 숫자로 답할 수 없다.
        ->and($notice->recipients->pluck('id')->all())->toBe([$this->vn->id]);
});

it('아무도 고르지 않으면 보내지 않는다', function () {
    sendNotice(['target' => Notice::TARGET_SELECTED, 'worker_ids' => []])
        ->assertSessionHasErrors('worker_ids');

    expect(Notice::count())->toBe(0);
    Notification::assertNothingSent();
});

it('받을 사람이 없으면 공지를 만들지 않는다', function () {
    // 대상이 비었는데 이력만 남으면 보낸 줄 안다.
    Worker::query()->update(['status' => WorkerStatus::Returned->value]);

    sendNotice()->assertSessionHasErrors('target');

    expect(Notice::count())->toBe(0);
});

it('범위로 보낸 공지는 수신자 표에 쌓지 않는다', function () {
    // 96명에게 보낼 때마다 96줄을 남기면 표가 공지 이력이 아니라 발송 로그가 된다.
    sendNotice()->assertRedirect();

    expect(Notice::firstOrFail()->recipients()->count())->toBe(0)
        ->and(Notice::firstOrFail()->recipients_count)->toBe(2);
});

it('근로자 앱 목록은 전체·선택 공지를 함께 반영한다', function () {
    sendNotice(['target' => Notice::TARGET_EVERYONE, 'title' => '전체 공지'])->assertRedirect();
    sendNotice([
        'target' => Notice::TARGET_SELECTED,
        'worker_ids' => [$this->bd->id],
        'title' => '콕 집은 공지',
    ])->assertRedirect();

    $vnTitles = actingAs($this->vn, 'sanctum')->getJson('/api/v1/notices')
        ->assertOk()->json('data.*.title');
    $bdTitles = actingAs($this->bd, 'sanctum')->getJson('/api/v1/notices')
        ->assertOk()->json('data.*.title');

    expect($vnTitles)->toContain('전체 공지')
        ->not->toContain('콕 집은 공지')
        ->and($bdTitles)->toContain('전체 공지')
        ->and($bdTitles)->toContain('콕 집은 공지');
});

it('관리자가 아니면 공지를 보낼 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    actingAs($officer)->post(route('admin.notices.store'), [
        'title' => '몰래', 'body' => '보내기', 'target' => Notice::TARGET_ALL,
    ])->assertForbidden();

    expect(Notice::count())->toBe(0);
});

it('발송 화면에 다섯 가지 대상이 모두 나온다', function () {
    $html = actingAs($this->admin)->get(url('admin/screen/notices'))->assertOk()->getContent();

    foreach (Notice::targetOptions() as $label) {
        expect($html)->toContain($label);
    }

    // 앱을 안 깐 근로자는 푸시를 못 받는다 — 고르기 전에 드러나야 한다.
    expect($html)->toContain('앱 미설치');
});
