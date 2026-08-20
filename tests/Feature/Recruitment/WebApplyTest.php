<?php

declare(strict_types=1);

use App\Domains\Demand\Models\City;
use App\Domains\Recruitment\Enums\ScreeningStatus;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use App\Domains\Recruitment\Notifications\SupplementRequestedNotification;
use App\Domains\Recruitment\Notifications\WorkerPassedNotification;
use App\Domains\Recruitment\Notifications\WorkerRejectedNotification;
use App\Http\Controllers\Admin\SignupApprovalController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * 웹 근로자 가입 — 신청 → 심사(보완/합격/보류/불합격) → 합격자 본인 화면.
 *
 * 앱과 같은 Action 을 타는지, 그리고 개인정보가 나가면 안 되는 통로(메일·공개
 * 화면)를 실제로 막고 있는지를 본다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
    Storage::fake(WorkerFile::DISK);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);

    // 이름을 못 박는다. CityFactory 는 고정 목록에서 뽑아 두 지역이 겹칠 수 있다.
    $this->city = City::factory()->create([
        'name' => '모집중군', 'region' => '테스트도', 'recruiting' => true, 'quota' => null,
    ]);
});

function applyBody(array $override = []): array
{
    return array_merge([
        'name' => 'Nguyen Van Web',
        'email' => 'web.apply@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'nationality' => 'VN',
        'city_id' => test()->city->id,
        'locale' => 'vi',
        'passport_no' => 'W1234567',
    ], $override);
}

it('웹에서 신청하면 승인 대기로 접수된다', function () {
    post(route('site.apply.store'), applyBody())
        ->assertRedirect(route('site.apply.done'));

    $worker = Worker::where('email', 'web.apply@example.com')->firstOrFail();

    expect($worker->status)->toBe(WorkerStatus::Pending)
        ->and($worker->screening())->toBe(ScreeningStatus::Received)
        // 앱과 같은 Action 을 타므로 여권번호도 같은 방식으로 저장된다(§7-1).
        ->and($worker->passport_no)->toBe('W1234567')
        ->and(Worker::wherePassport('W1234567')->exists())->toBeTrue();
});

it('파일이 없어도 신청할 수 있다', function () {
    // 현지에서 스캔본을 바로 구하지 못하는 경우가 많다. 막으면 신청 자체가 끊긴다.
    post(route('site.apply.store'), applyBody())->assertRedirect(route('site.apply.done'));

    expect(WorkerFile::count())->toBe(0)
        ->and(Worker::where('email', 'web.apply@example.com')->exists())->toBeTrue();
});

it('여러 파일을 유형 구분 없이 함께 올릴 수 있다', function () {
    post(route('site.apply.store'), applyBody([
        'documents' => [
            UploadedFile::fake()->create('여권 사본.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('범죄경력증명.pdf', 100, 'application/pdf'),
        ],
    ]))->assertRedirect(route('site.apply.done'));

    $worker = Worker::where('email', 'web.apply@example.com')->firstOrFail();
    $files = WorkerFile::where('worker_id', $worker->id)->get();

    expect($files)->toHaveCount(2)
        // 분류는 담당자가 한다 — 근로자가 고른 잘못된 분류는 없느니만 못하다.
        ->and($files->pluck('type')->unique()->pluck('value')->all())->toBe(['other'])
        // 화면에는 올린 이름 그대로, 저장은 ASCII 로.
        ->and($files->pluck('original_name')->all())->toContain('여권 사본.pdf')
        ->and(basename($files[0]->path))->toMatch('/^apply_[A-Za-z0-9]+\.pdf$/')
        // 본인이 올린 것과 관리자가 올린 것을 구분한다.
        ->and($files[0]->uploaded_by)->toBeNull();

    Storage::disk(WorkerFile::DISK)->assertExists($files[0]->path);
});

it('같은 여권번호로 두 번 신청할 수 없다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect(route('site.apply.done'));

    post(route('site.apply.store'), applyBody(['email' => 'other@example.com']))
        ->assertSessionHasErrors('passport_no');

    expect(Worker::count())->toBe(1);
});

it('모집이 닫힌 지역은 고를 수 없다', function () {
    $closed = City::factory()->create(['name' => '마감군', 'region' => '테스트도', 'recruiting' => false]);

    post(route('site.apply.store'), applyBody(['city_id' => $closed->id]))
        ->assertSessionHasErrors('city_id');

    // 화면 선택지에도 나오지 않는다 — 다 쓰고 나서 막히면 안 된다.
    $res = get(route('site.apply'))->assertOk();
    expect($res->getContent())->not->toContain($closed->label());
});

it('실행 파일이나 너무 큰 파일은 받지 않는다', function () {
    post(route('site.apply.store'), applyBody([
        'documents' => [UploadedFile::fake()->create('payload.php', 10, 'application/x-php')],
    ]))->assertSessionHasErrors();

    expect(Worker::count())->toBe(0);
});

/* ---------------- 심사 ---------------- */

it('합격하면 계정이 함께 열리고 합격 알림이 나간다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)
        ->postJson(route('admin.signups.screen', $worker), ['decision' => 'passed'])
        ->assertOk();

    $worker->refresh();

    expect($worker->screening_status)->toBe(ScreeningStatus::Passed)
        // 합격시켜 놓고 계정을 따로 열면 담당자가 반드시 한쪽을 잊는다.
        ->and($worker->status)->toBe(WorkerStatus::Active)
        ->and($worker->status->canLogin())->toBeTrue()
        ->and($worker->approved_at)->not->toBeNull()
        ->and($worker->screened_by)->toBe($this->admin->id);

    Notification::assertSentTo($worker, WorkerPassedNotification::class);
});

it('불합격하면 로그인이 막힌다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.screen', $worker), [
        'decision' => 'failed',
        'note' => '나이 조건 미달',
    ])->assertOk();

    $worker->refresh();

    expect($worker->status)->toBe(WorkerStatus::Rejected)
        ->and($worker->status->canLogin())->toBeFalse()
        ->and($worker->screening_note)->toBe('나이 조건 미달');

    Notification::assertSentTo($worker, WorkerRejectedNotification::class);
});

it('보류는 아무것도 바꾸지 않고 표시만 남긴다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.screen', $worker), [
        'decision' => 'held',
        'note' => '면접 결과 대기',
    ])->assertOk();

    $worker->refresh();

    expect($worker->screening_status)->toBe(ScreeningStatus::Held)
        ->and($worker->status)->toBe(WorkerStatus::Pending);

    Notification::assertNothingSent();
});

it('이미 처리된 신청은 다시 심사할 수 없다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.screen', $worker), ['decision' => 'passed'])->assertOk();
    actingAs($this->admin)->postJson(route('admin.signups.screen', $worker), ['decision' => 'failed'])
        ->assertStatus(422);

    expect($worker->refresh()->status)->toBe(WorkerStatus::Active);
});

it('관리자가 아니면 심사할 수 없다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    actingAs($officer)->postJson(route('admin.signups.screen', $worker), ['decision' => 'passed'])
        ->assertForbidden();

    expect($worker->refresh()->status)->toBe(WorkerStatus::Pending);
});

/* ---------------- 보완 요청 ---------------- */

it('보완을 요청하면 근로자 언어로 메일이 가고 개인정보가 실리지 않는다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.supplement', $worker), [
        'items' => ['여권 사본', '범죄경력 증명서'],
        'note' => '여권 사진면이 흐립니다',
    ])->assertOk();

    $worker->refresh();

    expect($worker->screening_status)->toBe(ScreeningStatus::SupplementRequested)
        ->and($worker->supplement_items)->toBe(['여권 사본', '범죄경력 증명서'])
        ->and($worker->status)->toBe(WorkerStatus::Pending);

    Notification::assertSentTo($worker, SupplementRequestedNotification::class,
        function (SupplementRequestedNotification $n) use ($worker) {
            $text = implode(' ', $n->outboundStrings());

            // 이름·여권번호가 메일에 없어야 한다(§7-3). 개수와 링크뿐이다.
            expect($text)->not->toContain($worker->name)
                ->and($text)->not->toContain('W1234567')
                // 무엇이 부족한지도 본문에 적지 않는다 — 링크를 열어야 보인다.
                ->and($text)->not->toContain('여권 사본')
                // 담당자 메모도 나가지 않는다.
                ->and($text)->not->toContain('여권 사진면이 흐립니다')
                ->and($n->count)->toBe(2)
                // 근로자 언어(vi)로 렌더된다(§6).
                ->and($text)->toContain('bổ sung');

            return true;
        });
});

it('보완 요청 항목을 고르지 않으면 보내지 않는다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.supplement', $worker), ['items' => []])
        ->assertStatus(422);

    Notification::assertNothingSent();
});

it('보완 링크로 들어와 파일을 더 내면 다시 접수 상태가 된다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.supplement', $worker), [
        'items' => ['여권 사본'],
    ])->assertOk();

    $url = URL::temporarySignedRoute('site.apply.supplement.store', now()->addDay(), ['worker' => $worker->id]);

    post($url, [
        'documents' => [UploadedFile::fake()->create('여권 재촬영.pdf', 100, 'application/pdf')],
        'phone_home_country' => '+84 90 1234 567',
    ])->assertRedirect(route('site.apply.done'));

    $worker->refresh();

    expect(WorkerFile::where('worker_id', $worker->id)->count())->toBe(1)
        ->and($worker->phone_home_country)->toBe('+84 90 1234 567')
        // 다시 담당자 차례다.
        ->and($worker->screening_status)->toBe(ScreeningStatus::Received)
        ->and($worker->supplement_items)->toBeNull();
});

it('보완 화면의 제출 버튼이 실제로 동작한다', function () {
    // 화면은 열리는데 제출만 막히는 상태가 있었다 — 폼 action 에 서명이 없었다.
    // 라우트 이름으로 다시 만들지 말고 **화면이 내놓은 action 을 그대로** 눌러 본다.
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.supplement', $worker), [
        'items' => ['여권 사본'],
    ])->assertOk();

    $html = get(URL::temporarySignedRoute('site.apply.supplement', now()->addDay(), ['worker' => $worker->id]))
        ->assertOk()->getContent();

    preg_match('/<form[^>]+action="([^"]+)"/', $html, $m);
    $action = html_entity_decode($m[1] ?? '');

    expect($action)->toContain('signature=');

    post($action, [
        'documents' => [UploadedFile::fake()->create('보완.pdf', 50, 'application/pdf')],
    ])->assertRedirect(route('site.apply.done'));

    expect(WorkerFile::where('worker_id', $worker->id)->count())->toBe(1);
});

it('서명 없는 보완 주소는 열리지 않는다', function () {
    // 링크가 위조되면 남의 신청에 파일을 넣을 수 있다.
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    get(route('site.apply.supplement', $worker))->assertForbidden();
});

it('보완 화면은 이미 낸 민감정보를 보여 주지 않는다', function () {
    // 로그인 없이 열리는 화면이다. 링크가 새어도 읽히는 것이 없어야 한다(§7-1).
    post(route('site.apply.store'), applyBody(['birth_date' => '1990-05-05']))->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.supplement', $worker), [
        'items' => ['여권 사본'],
    ])->assertOk();

    $html = get(URL::temporarySignedRoute('site.apply.supplement', now()->addDay(), ['worker' => $worker->id]))
        ->assertOk()->getContent();

    expect($html)->not->toContain('W1234567')
        ->and($html)->not->toContain('1990-05-05')
        // 요청받은 항목은 보여 준다 — 무엇을 내야 하는지는 알아야 한다.
        ->and($html)->toContain('여권 사본');
});

it('이미 처리된 신청의 보완 링크는 만료된다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.screen', $worker), ['decision' => 'passed'])->assertOk();

    get(URL::temporarySignedRoute('site.apply.supplement', now()->addDay(), ['worker' => $worker->id]))
        ->assertStatus(410);
});

it('심사 결정이 감사 기록에 남는다', function () {
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.screen', $worker), ['decision' => 'passed'])->assertOk();

    $log = Activity::where('log_name', 'worker-account')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['screening'])->toBe('passed')
        ->and($log->causer_id)->toBe($this->admin->id);
});

it('가입 승인 목록에 서류 개수와 진행 상태가 함께 나온다', function () {
    post(route('site.apply.store'), applyBody([
        'documents' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
    ]))->assertRedirect();

    $rows = SignupApprovalController::rows();

    expect($rows)->toHaveCount(1)
        // 하나하나 열어 보지 않고도 보완이 필요한 신청을 골라낼 수 있어야 한다.
        ->and($rows[0]['files'])->toBe(1)
        ->and($rows[0]['screening_label'])->toBe('접수');
});

it('관리자 상세에는 여권번호·생년월일·전화가 그대로 나오고 열람 기록이 남는다', function () {
    // 가려 두면 담당자가 엑셀·메신저로 옮겨 적게 된다. 대신 누가 봤는지 남긴다(§7-6).
    post(route('site.apply.store'), applyBody([
        'birth_date' => '1993-07-08',
        'phone_home_country' => '+84 90 000 1111',
    ]))->assertRedirect();

    $worker = Worker::firstOrFail();

    actingAs($this->admin)->getJson(route('admin.signups.show', $worker))
        ->assertOk()
        ->assertJsonPath('passport_no', 'W1234567')
        ->assertJsonPath('birth_date', '1993-07-08')
        ->assertJsonPath('phone_home_country', '+84 90 000 1111');

    actingAs($this->admin)->getJson(url('admin/screen/workers/'.$worker->id.'?format=json'))
        ->assertOk()
        ->assertJsonPath('passport_no', 'W1234567')
        ->assertJsonPath('birth_date', '1993-07-08');

    expect(Activity::where('log_name', 'personal-data-access')->count())->toBeGreaterThanOrEqual(2);
});

it('로그·배열 변환에서는 여전히 가려진다', function () {
    // 화면에 보여 주는 것과 로그에 남기는 것은 다른 문제다(§7-1).
    post(route('site.apply.store'), applyBody())->assertRedirect();

    $array = Worker::firstOrFail()->toArray();

    expect($array['passport_no'])->not->toBe('W1234567');
});

it('보완 링크에서도 모든 정보를 고칠 수 있다', function () {
    $city = City::factory()->create([
        'name' => '옮긴군', 'region' => '테스트도', 'recruiting' => true, 'quota' => null,
    ]);

    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    $url = URL::temporarySignedRoute('site.apply.supplement.store', now()->addDay(), ['worker' => $worker->id]);

    post($url, [
        'name' => 'Nguyen Van Fixed',
        'nationality' => 'bd',
        'locale' => 'bn',
        'city_id' => $city->id,
        'passport_no' => 'FIXED0001',
        'birth_date' => '1991-02-03',
        'phone_home_country' => '+880 1 2345',
        'documents' => [UploadedFile::fake()->create('보완.pdf', 30, 'application/pdf')],
    ])->assertRedirect(route('site.apply.done'));

    $w = $worker->refresh();

    expect($w->name)->toBe('Nguyen Van Fixed')
        ->and($w->nationality)->toBe('BD')
        ->and($w->locale)->toBe('bn')
        ->and($w->city_id)->toBe($city->id)
        ->and($w->passport_no)->toBe('FIXED0001')
        ->and($w->birth_date)->toBe('1991-02-03')
        ->and(WorkerFile::where('worker_id', $w->id)->count())->toBe(1);
});

it('보완 화면은 민감 항목을 비워 두고 나머지만 채워 준다', function () {
    // 로그인 없이 열리는 화면이라 여권번호를 되돌려 보여 주지 않는다(§7-1).
    // 대신 새로 적으면 바뀐다 — 고칠 수 없는 것은 아니다.
    post(route('site.apply.store'), applyBody(['name' => 'Nguyen Prefill']))->assertRedirect();
    $worker = Worker::firstOrFail();

    actingAs($this->admin)->postJson(route('admin.signups.supplement', $worker), [
        'items' => ['여권 사본'],
    ])->assertOk();

    $html = get(URL::temporarySignedRoute('site.apply.supplement', now()->addDay(), ['worker' => $worker->id]))
        ->assertOk()->getContent();

    expect($html)->toContain('Nguyen Prefill')   // 이름은 채워 준다
        ->not->toContain('W1234567');            // 여권번호는 비워 둔다
});

it('보완 요청 메일이 실패하면 상태를 바꾸지 않는다', function () {
    // 큐를 안 쓰므로 발송이 그 자리에서 터진다. 상태를 먼저 바꿔 두면
    // '보완 요청함' 으로 남고 메일은 안 간 상태가 된다 — 담당자는 보낸 줄 안다.
    post(route('site.apply.store'), applyBody())->assertRedirect();
    $worker = Worker::firstOrFail();

    $this->mock(Dispatcher::class, function ($m) {
        $m->shouldReceive('send')->andThrow(new RuntimeException('SMTP 연결 실패'));
        $m->shouldReceive('sendNow')->andThrow(new RuntimeException('SMTP 연결 실패'));
    });

    actingAs($this->admin)->postJson(route('admin.signups.supplement', $worker), [
        'items' => ['여권 사본'],
    ])->assertStatus(422)->assertJsonPath('message', 'SMTP 연결 실패');

    $worker->refresh();

    expect($worker->screening())->toBe(ScreeningStatus::Received)
        ->and($worker->supplement_items)->toBeNull()
        ->and($worker->supplement_requested_at)->toBeNull();
});
