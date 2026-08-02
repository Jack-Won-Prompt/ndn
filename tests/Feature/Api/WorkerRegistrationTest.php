<?php

declare(strict_types=1);

use App\Domains\Demand\Models\City;
use App\Domains\Recruitment\Actions\ApproveWorkerAction;
use App\Domains\Recruitment\Actions\RejectWorkerAction;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;

/**
 * 근로자 셀프 가입(관리자 승인제) — 등록 API + 승인/거절 Action (CLAUDE.md §9, §7).
 */
function registerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Nguyen Van Test',
        'email' => 'applicant@ndn.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'nationality' => 'vn',
        // 지원 지자체 — 지역별 모집이라 가입 시 필수로 고른다.
        'city_id' => City::factory()->create()->id,
        'locale' => 'vi',
        'passport_no' => 'C1234567',
    ], $overrides);
}

it('셀프 가입하면 승인 대기 상태로 생성되고 토큰은 발급하지 않는다', function () {
    $res = $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertStatus(201)
        ->assertJsonPath('data.status', WorkerStatus::Pending->value);

    // 승인 전에는 토큰이 없어야 한다
    expect($res->json('meta.token'))->toBeNull();

    $worker = Worker::where('email', 'applicant@ndn.test')->first();
    expect($worker)->not->toBeNull();
    expect($worker->status)->toBe(WorkerStatus::Pending);
    expect($worker->nationality)->toBe('VN');           // 대문자 정규화
});

it('가입 시 고른 지원 지자체가 저장된다', function () {
    $city = City::factory()->create(['name' => '당진시', 'region' => '충청남도']);

    $this->postJson('/api/v1/auth/register', registerPayload(['city_id' => $city->id]))
        ->assertStatus(201);

    $worker = Worker::where('email', 'applicant@ndn.test')->firstOrFail();
    expect($worker->city_id)->toBe($city->id);
    expect($worker->city->name)->toBe('당진시');
});

it('지역을 고르지 않거나 없는 지역이면 가입에 실패한다', function () {
    $payload = registerPayload();
    unset($payload['city_id']);

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertStatus(422)->assertJsonValidationErrorFor('city_id');

    $this->postJson('/api/v1/auth/register', registerPayload(['city_id' => 999999]))
        ->assertStatus(422)->assertJsonValidationErrorFor('city_id');
});

it('가입 화면용 지역 목록을 인증 없이 조회할 수 있다', function () {
    City::factory()->create(['name' => '당진시', 'region' => '충청남도']);
    City::factory()->create(['name' => '여주시', 'region' => '경기도']);

    $res = $this->getJson('/api/v1/cities')->assertOk();

    expect($res->json('meta.count'))->toBe(2);
    // region → name 순 정렬: 경기도 여주시가 먼저
    expect($res->json('data.0.label'))->toBe('경기도 여주시');
    expect($res->json('data.1.label'))->toBe('충청남도 당진시');
});

it('가입 응답 본문에 비밀번호·여권번호가 노출되지 않는다', function () {
    $res = $this->postJson('/api/v1/auth/register', registerPayload())->assertStatus(201);

    expect($res->json('data'))->not->toHaveKey('password');
    expect($res->json('data'))->not->toHaveKey('passport_no');
});

it('승인 대기 계정은 승인 전에는 로그인할 수 없다', function () {
    $this->postJson('/api/v1/auth/register', registerPayload())->assertStatus(201);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'applicant@ndn.test',
        'password' => 'password123',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');
});

it('관리자가 승인하면 로그인할 수 있다', function () {
    $this->postJson('/api/v1/auth/register', registerPayload())->assertStatus(201);
    $worker = Worker::where('email', 'applicant@ndn.test')->firstOrFail();

    app(ApproveWorkerAction::class)->execute($worker, User::factory()->create());

    $this->postJson('/api/v1/auth/login', [
        'email' => 'applicant@ndn.test',
        'password' => 'password123',
    ])->assertOk()->assertJsonPath('data.id', $worker->id);
});

it('이미 등록된 여권번호로는 가입할 수 없다', function () {
    Worker::factory()->create(['passport_no' => 'C7654321']);

    $this->postJson('/api/v1/auth/register', registerPayload([
        'email' => 'other@ndn.test',
        'passport_no' => 'C7654321',
    ]))->assertStatus(422)->assertJsonValidationErrorFor('passport_no');
});

it('이미 사용 중인 이메일로는 가입할 수 없다', function () {
    Worker::factory()->create(['email' => 'taken@ndn.test']);

    $this->postJson('/api/v1/auth/register', registerPayload([
        'email' => 'taken@ndn.test',
        'passport_no' => 'C9999999',
    ]))->assertStatus(422)->assertJsonValidationErrorFor('email');
});

it('비밀번호 확인이 일치하지 않으면 가입에 실패한다', function () {
    $this->postJson('/api/v1/auth/register', registerPayload([
        'password_confirmation' => 'mismatch',
    ]))->assertStatus(422)->assertJsonValidationErrorFor('password');
});

it('승인 Action 은 승인자·시각을 기록한다', function () {
    $worker = Worker::factory()->create(['status' => WorkerStatus::Pending]);
    $admin = User::factory()->create();

    app(ApproveWorkerAction::class)->execute($worker, $admin);

    $worker->refresh();
    expect($worker->status)->toBe(WorkerStatus::Active);
    expect($worker->approved_by)->toBe($admin->id);
    expect($worker->approved_at)->not->toBeNull();
});

it('승인 대기가 아닌 계정은 승인/거절할 수 없다', function () {
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);
    $admin = User::factory()->create();

    expect(fn () => app(ApproveWorkerAction::class)->execute($worker, $admin))
        ->toThrow(RuntimeException::class);
    expect(fn () => app(RejectWorkerAction::class)->execute($worker, $admin))
        ->toThrow(RuntimeException::class);
});

it('거절 Action 은 상태를 rejected 로 바꾼다', function () {
    $worker = Worker::factory()->create(['status' => WorkerStatus::Pending]);

    app(RejectWorkerAction::class)->execute($worker, User::factory()->create(), '서류 미비');

    expect($worker->refresh()->status)->toBe(WorkerStatus::Rejected);
});
