<?php

declare(strict_types=1);

use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use App\Domains\Recruitment\Notifications\WorkerResetPasswordNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * 근로자 웹 로그인 · 본인 화면 · 비밀번호 찾기.
 *
 * 이 화면의 값어치는 **보이지 않는 것**에 있다. 합격자만 들어오고, 들어와서도
 * 자기 근무지와 본인 정보 말고는 아무것도 볼 수 없어야 한다.
 */
beforeEach(function () {
    Notification::fake();
    Storage::fake(WorkerFile::DISK);

    $this->worker = Worker::factory()->create([
        'name' => 'Nguyen Van Pass',
        'email' => 'pass@example.com',
        'password' => 'password123',
        'status' => WorkerStatus::Active->value,
        'passport_no' => 'P7654321',
        'birth_date' => '1992-03-04',
    ]);
});

it('합격한 근로자는 웹에 로그인할 수 있다', function () {
    post(route('worker.login.attempt'), [
        'email' => 'pass@example.com',
        'password' => 'password123',
    ])->assertRedirect(route('worker.home'));

    expect(auth('worker')->check())->toBeTrue();
});

it('승인 전 근로자는 로그인할 수 없다', function () {
    // 승인 대기로 로그인이 되면 아직 결과가 안 났는데 난 것처럼 보인다.
    $pending = Worker::factory()->create([
        'email' => 'pending@example.com',
        'password' => 'password123',
        'status' => WorkerStatus::Pending->value,
        'locale' => 'ko',
    ]);

    post(route('worker.login.attempt'), [
        'email' => 'pending@example.com',
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    expect(auth('worker')->check())->toBeFalse();
});

it('불합격·이탈 계정은 이유를 알려 주지 않는다', function () {
    // 로그인 화면이 '이 사람이 불합격했다' 를 알려 주는 곳이 되면 안 된다.
    $rejected = Worker::factory()->create([
        'email' => 'rejected@example.com',
        'password' => 'password123',
        'status' => WorkerStatus::Rejected->value,
    ]);

    $res = post(route('worker.login.attempt'), [
        'email' => 'rejected@example.com',
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toBe(__('auth.failed'));
});

it('비밀번호가 틀리면 계정이 있는지 알려 주지 않는다', function () {
    $wrong = post(route('worker.login.attempt'), [
        'email' => 'pass@example.com', 'password' => 'nope-nope',
    ])->assertSessionHasErrors('email');

    $missing = post(route('worker.login.attempt'), [
        'email' => 'nobody@example.com', 'password' => 'nope-nope',
    ])->assertSessionHasErrors('email');

    // 두 응답이 같아야 이 화면이 가입 여부 조회기가 되지 않는다.
    expect(session('errors')->first('email'))->toBe(__('auth.failed'));
});

it('로그인하지 않으면 본인 화면이 열리지 않는다', function () {
    get(route('worker.home'))->assertRedirect();
});

it('본인 화면에 근무지와 입국 일정이 나온다', function () {
    $farm = Farm::factory()->create(['name' => '한아름농장', 'main_crop' => '딸기']);
    $placement = Placement::factory()->create([
        'worker_id' => $this->worker->id,
        'farm_id' => $farm->id,
        'status' => PlacementStatus::Confirmed->value,
    ]);
    ArrivalRecord::factory()->create([
        'placement_id' => $placement->id,
        'flight_no' => 'VJ982',
    ]);

    $html = actingAs($this->worker, 'worker')->get(route('worker.home'))->assertOk()->getContent();

    expect($html)->toContain('한아름농장')
        ->toContain('딸기')
        ->toContain('VJ982');
});

it('본인 화면에 여권번호·생년월일을 보여 주지 않는다', function () {
    // 로그인만 하면 열리는 화면이다. 굳이 띄울 이유가 없다(§7-1).
    $html = actingAs($this->worker, 'worker')->get(route('worker.home'))->assertOk()->getContent();

    expect($html)->toContain('Nguyen Van Pass')
        ->not->toContain('P7654321')
        ->and($html)->not->toContain('1992-03-04');
});

it('배정이 없으면 아직 정해지지 않았다고만 말한다', function () {
    $html = actingAs($this->worker, 'worker')->get(route('worker.home'))->assertOk()->getContent();

    expect($html)->toContain('아직 근무지가 정해지지 않았습니다');
});

it('남의 서류는 내려받을 수 없다', function () {
    $other = Worker::factory()->create(['status' => WorkerStatus::Active->value]);
    $file = WorkerFile::factory()->create(['worker_id' => $other->id]);

    actingAs($this->worker, 'worker')->get(route('worker.files.show', $file))->assertNotFound();
});

it('내 서류는 내려받을 수 있다', function () {
    Storage::disk(WorkerFile::DISK)->put('worker-files/x/mine.pdf', 'PDF');

    $file = WorkerFile::factory()->create([
        'worker_id' => $this->worker->id,
        'path' => 'worker-files/x/mine.pdf',
    ]);

    actingAs($this->worker, 'worker')->get(route('worker.files.show', $file))->assertOk();
});

it('로그아웃하면 세션이 끊긴다', function () {
    actingAs($this->worker, 'worker')->post(route('worker.logout'))->assertRedirect(route('site.home'));

    expect(auth('worker')->check())->toBeFalse();
});

/* ---------------- 비밀번호 찾기 ---------------- */

it('재설정 링크는 근로자 화면 주소로 나가고 개인정보가 없다', function () {
    post(route('worker.password.email'), ['email' => 'pass@example.com'])
        ->assertSessionHas('status');

    Notification::assertSentTo($this->worker, WorkerResetPasswordNotification::class,
        function (WorkerResetPasswordNotification $n) {
            // 기본 알림을 쓰면 관리자 Fortify 주소로 가서 근로자가 열 수 없다.
            expect($n->resetUrl)->toContain('/worker/reset-password/');

            $text = implode(' ', $n->outboundStrings());
            expect($text)->not->toContain('Nguyen Van Pass')
                ->and($text)->not->toContain('P7654321');

            return true;
        });
});

it('가입되지 않은 주소여도 같은 안내를 준다', function () {
    // 다르게 답하면 이 화면이 가입 여부 조회기가 된다.
    post(route('worker.password.email'), ['email' => 'nobody@example.com'])
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('재설정 토큰으로 비밀번호를 바꾸고 새 비밀번호로 로그인한다', function () {
    $token = Password::broker('workers')->createToken($this->worker);

    post(route('worker.password.update'), [
        'token' => $token,
        'email' => 'pass@example.com',
        'password' => 'brand-new-pw',
        'password_confirmation' => 'brand-new-pw',
    ])->assertRedirect(route('worker.login'));

    post(route('worker.login.attempt'), [
        'email' => 'pass@example.com',
        'password' => 'brand-new-pw',
    ])->assertRedirect(route('worker.home'));

    expect(auth('worker')->check())->toBeTrue();
});

it('근로자 재설정 토큰은 관리자 표를 건드리지 않는다', function () {
    // 같은 이메일을 쓰는 담당자가 있으면 한쪽 재설정이 다른 쪽 토큰을 지운다.
    Password::broker('workers')->createToken($this->worker);

    expect(DB::table('worker_password_reset_tokens')->count())->toBe(1)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
});

/* ---------------- 본인 정보 수정 ---------------- */

it('본인 화면에서 모든 정보를 고칠 수 있다', function () {
    $city = City::factory()->create([
        'name' => '수정군', 'region' => '테스트도', 'recruiting' => true, 'quota' => null,
    ]);

    actingAs($this->worker, 'worker')->post(route('worker.profile.update'), [
        'name' => 'Nguyen Van Changed',
        'nationality' => 'la',
        'locale' => 'lo',
        'city_id' => $city->id,
        'passport_no' => 'CHANGED999',
        'birth_date' => '1990-01-02',
        'phone_home_country' => '+856 20 111 2222',
    ])->assertRedirect(route('worker.home'));

    $w = $this->worker->refresh();

    expect($w->name)->toBe('Nguyen Van Changed')
        // 국적은 대문자로 맞춘다 — 매칭이 대문자 코드로 대조한다.
        ->and($w->nationality)->toBe('LA')
        ->and($w->locale)->toBe('lo')
        ->and($w->city_id)->toBe($city->id)
        ->and($w->passport_no)->toBe('CHANGED999')
        ->and($w->birth_date)->toBe('1990-01-02')
        ->and($w->phone_home_country)->toBe('+856 20 111 2222');
});

it('수정 화면은 로그인한 본인에게 기존 값을 되돌려 준다', function () {
    // 보완 링크(로그인 없음)와 다른 점이다. 본인이 확인하고 고쳐야 한다.
    $html = actingAs($this->worker, 'worker')->get(route('worker.profile'))->assertOk()->getContent();

    expect($html)->toContain('P7654321')->toContain('1992-03-04');
});

it('빈 칸으로 기존 값을 지우지 않는다', function () {
    // 파일만 올리려던 사람의 여권번호가 사라지면 안 된다.
    actingAs($this->worker, 'worker')->post(route('worker.profile.update'), [
        'name' => '', 'passport_no' => '', 'birth_date' => '', 'phone_home_country' => '',
    ])->assertRedirect(route('worker.home'));

    $w = $this->worker->refresh();

    expect($w->name)->toBe('Nguyen Van Pass')
        ->and($w->passport_no)->toBe('P7654321')
        ->and($w->birth_date)->toBe('1992-03-04');
});

it('남이 쓰는 여권번호로는 바꿀 수 없다', function () {
    Worker::factory()->create(['passport_no' => 'TAKEN0001', 'status' => WorkerStatus::Active->value]);

    actingAs($this->worker, 'worker')->post(route('worker.profile.update'), [
        'passport_no' => 'TAKEN0001',
    ])->assertSessionHasErrors('passport_no');

    expect($this->worker->refresh()->passport_no)->toBe('P7654321');
});

it('모집이 닫힌 지역으로는 옮길 수 없다', function () {
    $closed = City::factory()->create([
        'name' => '마감군', 'region' => '테스트도', 'recruiting' => false,
    ]);

    actingAs($this->worker, 'worker')->post(route('worker.profile.update'), [
        'city_id' => $closed->id,
    ])->assertSessionHasErrors('city_id');
});

it('본인 화면에서 서류를 더 올릴 수 있고 기존 서류는 남는다', function () {
    WorkerFile::factory()->create(['worker_id' => $this->worker->id]);

    actingAs($this->worker, 'worker')->post(route('worker.profile.update'), [
        'documents' => [UploadedFile::fake()->create('추가서류.pdf', 50, 'application/pdf')],
    ])->assertRedirect(route('worker.home'));

    $files = WorkerFile::where('worker_id', $this->worker->id)->get();

    expect($files)->toHaveCount(2)
        ->and($files->pluck('original_name'))->toContain('추가서류.pdf')
        // 본인이 올린 것이라 uploaded_by 가 비어 있다.
        ->and($files->last()->uploaded_by)->toBeNull();
});

it('본인 정보 수정은 항목 이름만 감사 기록에 남긴다', function () {
    // 값을 남기면 감사 로그가 개인정보 보관소가 된다(§7-1).
    actingAs($this->worker, 'worker')->post(route('worker.profile.update'), [
        'passport_no' => 'AUDIT1234',
    ])->assertRedirect();

    $log = Activity::where('log_name', 'worker-account')->latest('id')->first();

    expect($log->properties['fields'])->toBe(['passport_no'])
        ->and(json_encode($log->properties))->not->toContain('AUDIT1234');
});

it('로그인하지 않으면 수정 화면이 열리지 않는다', function () {
    get(route('worker.profile'))->assertRedirect();
    post(route('worker.profile.update'), ['name' => '침입'])->assertRedirect();
});

it('로그인 안 된 근로자는 관리자 로그인이 아니라 근로자 로그인으로 간다', function () {
    // 근로자를 관리자 로그인 화면에 떨어뜨리면 자기 계정이 없는 줄 안다.
    get(route('worker.home'))->assertRedirect(route('worker.login'));
    get(route('worker.profile'))->assertRedirect(route('worker.login'));
});
