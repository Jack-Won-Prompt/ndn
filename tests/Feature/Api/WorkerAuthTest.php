<?php

declare(strict_types=1);

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * 근로자 앱 로그인 (CLAUDE.md §9).
 */
it('이메일·비밀번호로 로그인하면 Sanctum 토큰을 발급한다', function () {
    $worker = Worker::factory()->create([
        'email' => 'worker@ndn.test',
        'password' => 'password',
        'locale' => 'vi',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'worker@ndn.test',
        'password' => 'password',
        'device_name' => 'test-device',
    ])->assertOk()
        ->assertJsonPath('data.id', $worker->id)
        ->assertJsonPath('meta.locale', 'vi');

    expect($response->json('meta.token'))->toBeString()->not->toBeEmpty();
    expect($worker->tokens()->count())->toBe(1);
});

it('발급받은 토큰으로 보호된 엔드포인트에 접근할 수 있다', function () {
    $worker = Worker::factory()->create([
        'email' => 'worker@ndn.test',
        'password' => 'password',
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'worker@ndn.test',
        'password' => 'password',
    ])->json('meta.token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $worker->id);
});

it('비밀번호가 틀리면 로그인에 실패한다', function () {
    Worker::factory()->create(['email' => 'worker@ndn.test', 'password' => 'password']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'worker@ndn.test',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');
});

it('없는 계정과 틀린 비밀번호는 같은 오류를 낸다 (계정 존재 여부 노출 금지)', function () {
    Worker::factory()->create(['email' => 'worker@ndn.test', 'password' => 'password']);

    $missing = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@ndn.test',
        'password' => 'password',
    ])->assertStatus(422);

    $wrong = $this->postJson('/api/v1/auth/login', [
        'email' => 'worker@ndn.test',
        'password' => 'wrong-password',
    ])->assertStatus(422);

    expect($missing->json('errors.email'))->toBe($wrong->json('errors.email'));
});

it('활성이 아닌 계정은 로그인할 수 없다 (승인 대기·거절·정지·귀국)', function (WorkerStatus $status) {
    Worker::factory()->create([
        'email' => 'worker@ndn.test',
        'password' => 'password',
        'status' => $status,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'worker@ndn.test',
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');
})->with([
    WorkerStatus::Pending,
    WorkerStatus::Inactive,
    WorkerStatus::Returned,
    WorkerStatus::Rejected,
]);

it('승인 대기 계정은 자격증명 오류와 다른 안내를 받는다', function () {
    Worker::factory()->create([
        'email' => 'pending@ndn.test',
        'password' => 'password',
        'status' => WorkerStatus::Pending,
    ]);
    Worker::factory()->create([
        'email' => 'active@ndn.test',
        'password' => 'password',
        'status' => WorkerStatus::Active,
    ]);

    $pending = $this->postJson('/api/v1/auth/login', [
        'email' => 'pending@ndn.test', 'password' => 'password',
    ])->assertStatus(422);

    $wrongPassword = $this->postJson('/api/v1/auth/login', [
        'email' => 'active@ndn.test', 'password' => 'nope',
    ])->assertStatus(422);

    // 승인 대기는 "기다리세요", 비밀번호 오류는 "틀렸습니다" — 구분돼야 한다.
    expect($pending->json('errors.email'))
        ->not->toBe($wrongPassword->json('errors.email'));
});

it('로그인 응답에 비밀번호 해시가 새어나가지 않는다', function () {
    Worker::factory()->create(['email' => 'worker@ndn.test', 'password' => 'password']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'worker@ndn.test',
        'password' => 'password',
    ])->assertOk();

    expect($response->json('data'))->not->toHaveKey('password');
    expect($response->json('data'))->not->toHaveKey('passport_no');
});

it('로그아웃하면 현재 토큰이 폐기된다', function () {
    $worker = Worker::factory()->create(['email' => 'worker@ndn.test', 'password' => 'password']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'worker@ndn.test',
        'password' => 'password',
    ])->json('meta.token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    // 토큰 행 자체가 사라져야 한다 (같은 테스트 안의 후속 요청은 guard 가 사용자를
    // 캐시하므로 응답 코드가 아니라 저장소 상태로 검증한다).
    expect($worker->tokens()->count())->toBe(0);

    [, $plain] = explode('|', $token, 2);
    $this->assertDatabaseMissing('personal_access_tokens', [
        'token' => hash('sha256', $plain),
    ]);
});

it('같은 기기로 재로그인하면 이전 토큰을 정리한다', function () {
    Worker::factory()->create(['email' => 'worker@ndn.test', 'password' => 'password']);

    $first = $this->postJson('/api/v1/auth/login', [
        'email' => 'worker@ndn.test', 'password' => 'password', 'device_name' => 'phone',
    ])->json('meta.token');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'worker@ndn.test', 'password' => 'password', 'device_name' => 'phone',
    ])->assertOk();

    // 예전 토큰은 더 이상 통하지 않는다
    $this->withHeader('Authorization', 'Bearer '.$first)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('관리자 토큰으로는 근로자 로그아웃 엔드포인트를 쓸 수 없다', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/auth/logout')->assertForbidden();
});
