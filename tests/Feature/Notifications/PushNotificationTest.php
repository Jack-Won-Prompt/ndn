<?php

declare(strict_types=1);

use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Actions\ConfirmPlacementAction;
use App\Domains\Matching\Models\Placement;
use App\Domains\Matching\Notifications\PlacementConfirmedNotification;
use App\Domains\Onboarding\Actions\ReviewOnboardingAction;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Actions\ApproveWorkerAction;
use App\Domains\Recruitment\Actions\RejectWorkerAction;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Notifications\WorkerApprovedNotification;
use App\Domains\Recruitment\Notifications\WorkerRejectedNotification;
use App\Domains\Support\Actions\CreateSosAlertAction;
use App\Domains\Support\Notifications\SosAlertedNotification;
use App\Models\User;
use App\Shared\Enums\UserRole;
use App\Shared\Notifications\Channels\FcmChannel;
use App\Shared\Notifications\FcmSender;
use App\Shared\Notifications\Models\DeviceToken;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * 푸시 알림 (FCM).
 *
 * 푸시는 잠금화면에 그대로 뜨는 외부 채널이라 개인정보가 실리면 안 된다(§7-3).
 * 그 강제가 이 파일의 핵심이고, 나머지는 토큰 소유권 격리와 죽은 토큰 정리다.
 */
beforeEach(function () {
    foreach (UserRole::values() as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function pushWorker(string $locale = 'ko'): Worker
{
    return Worker::factory()->create([
        'status' => WorkerStatus::Active,
        'locale' => $locale,
    ]);
}

function pushAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::NdnAdmin->value);

    return $user;
}

// ── 기기 토큰 등록 ────────────────────────────────────────────────────────

it('근로자가 기기 토큰을 등록한다', function () {
    $worker = pushWorker();
    Sanctum::actingAs($worker);

    $this->postJson('/api/v1/device-tokens', [
        'token' => str_repeat('a', 140),
        'locale' => 'vi',
        'app_version' => '1.1.0',
    ])->assertCreated();

    $device = DeviceToken::first();

    expect($device->tokenable_id)->toBe($worker->id)
        ->and($device->tokenable_type)->toBe($worker->getMorphClass())
        ->and($device->locale)->toBe('vi');
});

it('같은 토큰을 다시 등록하면 행이 늘지 않는다', function () {
    $worker = pushWorker();
    Sanctum::actingAs($worker);

    $token = str_repeat('b', 140);

    $this->postJson('/api/v1/device-tokens', ['token' => $token])->assertCreated();
    $this->postJson('/api/v1/device-tokens', ['token' => $token])->assertOk();

    expect(DeviceToken::count())->toBe(1);
});

it('기기를 넘겨받으면 토큰의 주인이 바뀐다', function () {
    // 한 기기를 두 사람이 번갈아 쓰는 상황. 이전 사용자 행이 남으면
    // 그 사람의 알림이 새 사용자 화면에 뜬다.
    $token = str_repeat('c', 140);

    $first = pushWorker();
    Sanctum::actingAs($first);
    $this->postJson('/api/v1/device-tokens', ['token' => $token])->assertCreated();

    $second = pushWorker();
    Sanctum::actingAs($second);
    $this->postJson('/api/v1/device-tokens', ['token' => $token])->assertOk();

    expect(DeviceToken::count())->toBe(1)
        ->and(DeviceToken::first()->tokenable_id)->toBe($second->id);
});

it('관리자도 같은 방식으로 등록한다', function () {
    $admin = pushAdmin();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/device-tokens', ['token' => str_repeat('d', 140)])
        ->assertCreated();

    expect(DeviceToken::first()->tokenable_type)->toBe($admin->getMorphClass());
});

it('남의 토큰은 해제할 수 없다', function () {
    $token = str_repeat('e', 140);
    $owner = pushWorker();

    Sanctum::actingAs($owner);
    $this->postJson('/api/v1/device-tokens', ['token' => $token])->assertCreated();

    // 토큰 문자열만 알면 남의 등록을 지울 수 있으면 안 된다.
    Sanctum::actingAs(pushWorker());
    $this->deleteJson('/api/v1/device-tokens', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.deleted', false);

    expect(DeviceToken::count())->toBe(1);
});

it('본인 토큰은 해제된다', function () {
    $token = str_repeat('f', 140);
    $worker = pushWorker();
    Sanctum::actingAs($worker);

    $this->postJson('/api/v1/device-tokens', ['token' => $token])->assertCreated();
    $this->deleteJson('/api/v1/device-tokens', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(DeviceToken::count())->toBe(0);
});

it('토큰 등록에는 인증이 필요하다', function () {
    $this->postJson('/api/v1/device-tokens', ['token' => str_repeat('g', 140)])
        ->assertUnauthorized();
});

it('응답에 토큰 원문을 되돌려주지 않는다', function () {
    $token = str_repeat('h', 140);
    Sanctum::actingAs(pushWorker());

    $body = $this->postJson('/api/v1/device-tokens', ['token' => $token])
        ->assertCreated()
        ->getContent();

    expect($body)->not->toContain($token);
});

// ── 개인정보 가드 ─────────────────────────────────────────────────────────

it('개인정보 보장이 없는 알림은 푸시로 나갈 수 없다', function () {
    // 잠금화면에 이름·여권번호가 뜨는 사고를 코드 단계에서 막는다.
    $unsafe = new class extends BaseNotification
    {
        use Queueable;

        public function via(object $notifiable): array
        {
            return ['fcm'];
        }

        public function toFcm(object $notifiable): array
        {
            return ['title' => '홍길동 님', 'body' => 'M1234567'];
        }
    };

    $worker = pushWorker();
    DeviceToken::create([
        'tokenable_type' => $worker->getMorphClass(),
        'tokenable_id' => $worker->id,
        'token' => str_repeat('i', 140),
    ]);

    expect(fn () => app(FcmChannel::class)->send($worker, $unsafe))
        ->toThrow(LogicException::class);
})->group('guard');

it('푸시 문구에 개인정보 패턴이 없다 (5개 언어 전부)', function () {
    $patterns = [
        'passport' => '/\b[A-Z][0-9]{7,8}\b/',
        'phone' => '/\b01[0-9]-?[0-9]{3,4}-?[0-9]{4}\b/',
        'email' => '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',
        'rrn' => '/\b[0-9]{6}-?[1-4][0-9]{6}\b/',
    ];

    $notifications = [];
    foreach (['ko', 'vi', 'bn', 'lo', 'si'] as $locale) {
        $notifications[] = new WorkerApprovedNotification($locale);
        $notifications[] = new WorkerRejectedNotification($locale);
        $notifications[] = new PlacementConfirmedNotification($locale);
        $notifications[] = new \App\Domains\Onboarding\Notifications\OnboardingReviewedNotification(true, $locale);
        $notifications[] = new \App\Domains\Onboarding\Notifications\OnboardingReviewedNotification(false, $locale);
    }
    $notifications[] = new SosAlertedNotification;

    foreach ($notifications as $notification) {
        $text = implode(' ', $notification->outboundStrings());

        // 번역 키가 그대로 새어 나오면(키 누락) 사용자에게 'worker.push_...' 가 보인다.
        expect($text)->not->toContain('worker.push_');

        foreach ($patterns as $label => $pattern) {
            expect(preg_match($pattern, $text))->toBe(0, "푸시 문구에 {$label} 패턴: {$text}");
        }
    }
})->group('guard');

it('근로자 언어로 푸시 문구가 나간다', function () {
    $ko = (new WorkerApprovedNotification('ko'))->outboundStrings();
    $vi = (new WorkerApprovedNotification('vi'))->outboundStrings();

    expect($ko)->not->toBe($vi);
});

// ── 발송 지점 ─────────────────────────────────────────────────────────────

it('가입 승인·거절 시 근로자에게 알림이 간다', function () {
    Notification::fake();

    $admin = pushAdmin();
    $approved = Worker::factory()->create(['status' => WorkerStatus::Pending]);
    $rejected = Worker::factory()->create(['status' => WorkerStatus::Pending]);

    app(ApproveWorkerAction::class)->execute($approved, $admin);
    app(RejectWorkerAction::class)->execute($rejected, $admin, '서류 미비');

    Notification::assertSentTo($approved, WorkerApprovedNotification::class);
    Notification::assertSentTo($rejected, WorkerRejectedNotification::class);
});

it('온보딩 검수 결과가 근로자에게 간다', function () {
    Notification::fake();

    $worker = pushWorker();
    $submission = OnboardingSubmission::factory()->create([
        'worker_id' => $worker->id,
        'status' => OnboardingStatus::Submitted,
    ]);

    app(ReviewOnboardingAction::class)->execute(
        $submission,
        pushAdmin(),
        OnboardingStatus::Approved,
    );

    Notification::assertSentTo(
        $worker,
        \App\Domains\Onboarding\Notifications\OnboardingReviewedNotification::class,
    );
});

it('배정 확정 시 근로자에게 알림이 간다', function () {
    Notification::fake();

    $worker = pushWorker();
    $placement = Placement::factory()->create([
        'worker_id' => $worker->id,
        'farm_id' => Farm::factory()->create()->id,
    ]);

    app(ConfirmPlacementAction::class)->execute($placement, pushAdmin());

    Notification::assertSentTo($worker, PlacementConfirmedNotification::class);
});

it('SOS 접수 시 NDN 관리자에게만 긴급 알림이 간다', function () {
    Notification::fake();

    $ndnAdmin = pushAdmin();

    $cityOfficer = User::factory()->create();
    $cityOfficer->assignRole(UserRole::CityOfficer->value);

    app(CreateSosAlertAction::class)->execute(pushWorker(), 37.5, 127.0);

    Notification::assertSentTo($ndnAdmin, SosAlertedNotification::class);
    // 24시간 대응 책임은 NDN 에 있다 — 시청·농가까지 깨우지 않는다.
    Notification::assertNotSentTo($cityOfficer, SosAlertedNotification::class);
});

it('SOS 는 긴급 플래그로 나간다 (잠금화면을 깨워야 한다)', function () {
    $payload = (new SosAlertedNotification)->toFcm(pushAdmin());

    expect($payload['urgent'])->toBeTrue()
        ->and($payload['data']['screen'])->toBe('sos');
});

// ── 발송기 동작 ───────────────────────────────────────────────────────────

it('무효 토큰은 발송 실패 시 즉시 삭제된다', function () {
    // 앱 삭제·재설치로 죽은 토큰이 쌓이면 발송할 때마다 실패분을 계속 재시도하게 된다.
    Cache::put('fcm:access_token', 'test-token', 600);
    config(['fcm.credentials' => 'storage/app/firebase/service-account.json']);

    Http::fake([
        'fcm.googleapis.com/*' => Http::response([
            'error' => ['status' => 'UNREGISTERED', 'message' => 'not registered'],
        ], 404),
    ]);

    $worker = pushWorker();
    $device = DeviceToken::create([
        'tokenable_type' => $worker->getMorphClass(),
        'tokenable_id' => $worker->id,
        'token' => str_repeat('j', 140),
    ]);

    $sent = app(FcmSender::class)->send([$device], '제목', '본문');

    expect($sent)->toBe(0)
        ->and(DeviceToken::find($device->id))->toBeNull();
})->skip(
    fn () => ! is_readable(base_path('storage/app/firebase/service-account.json')),
    'FCM 서비스 계정 키가 없는 환경'
);

it('일시적 실패에서는 토큰을 지우지 않는다', function () {
    // 서버 장애(503)로 토큰을 지워 버리면 멀쩡한 기기가 알림을 영영 못 받는다.
    Cache::put('fcm:access_token', 'test-token', 600);
    config(['fcm.credentials' => 'storage/app/firebase/service-account.json']);

    Http::fake([
        'fcm.googleapis.com/*' => Http::response([
            'error' => ['status' => 'UNAVAILABLE', 'message' => 'try again'],
        ], 503),
    ]);

    $worker = pushWorker();
    $device = DeviceToken::create([
        'tokenable_type' => $worker->getMorphClass(),
        'tokenable_id' => $worker->id,
        'token' => str_repeat('k', 140),
    ]);

    app(FcmSender::class)->send([$device], '제목', '본문');

    expect(DeviceToken::find($device->id))->not->toBeNull();
})->skip(
    fn () => ! is_readable(base_path('storage/app/firebase/service-account.json')),
    'FCM 서비스 계정 키가 없는 환경'
);

it('키가 없으면 발송을 건너뛴다 (본 작업을 막지 않는다)', function () {
    config(['fcm.credentials' => 'storage/app/firebase/없는파일.json']);

    Http::fake();

    $worker = pushWorker();
    $device = DeviceToken::create([
        'tokenable_type' => $worker->getMorphClass(),
        'tokenable_id' => $worker->id,
        'token' => str_repeat('l', 140),
    ]);

    expect(app(FcmSender::class)->send([$device], '제목', '본문'))->toBe(0);

    Http::assertNothingSent();
});

it('data 값은 문자열로 변환해 보낸다', function () {
    // FCM 은 data 에 숫자가 들어오면 400 을 낸다.
    Cache::put('fcm:access_token', 'test-token', 600);
    config(['fcm.credentials' => 'storage/app/firebase/service-account.json']);

    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'], 200)]);

    $worker = pushWorker();
    $device = DeviceToken::create([
        'tokenable_type' => $worker->getMorphClass(),
        'tokenable_id' => $worker->id,
        'token' => str_repeat('m', 140),
    ]);

    app(FcmSender::class)->send([$device], '제목', '본문', ['screen' => 'sos', 'count' => 3]);

    Http::assertSent(function (ClientRequest $request) {
        $data = $request->data()['message']['data'];

        return $data['count'] === '3' && $data['screen'] === 'sos';
    });
})->skip(
    fn () => ! is_readable(base_path('storage/app/firebase/service-account.json')),
    'FCM 서비스 계정 키가 없는 환경'
);

it('기기가 없는 사용자에게는 발송을 시도하지 않는다', function () {
    Http::fake();

    app(FcmChannel::class)->send(pushWorker(), new WorkerApprovedNotification('ko'));

    Http::assertNothingSent();
});
