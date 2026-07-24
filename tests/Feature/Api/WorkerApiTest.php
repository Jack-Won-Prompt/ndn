<?php

declare(strict_types=1);

use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\SosAlert;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * 근로자 앱 API (CLAUDE.md §9).
 */
it('인증 없이는 /api/v1/me 에 접근할 수 없다', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('근로자 토큰으로 본인 프로필을 조회한다 (data/meta 구조)', function () {
    $worker = Worker::factory()->create(['name' => '아무개', 'locale' => 'vi']);
    Sanctum::actingAs($worker);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $worker->id)
        ->assertJsonPath('data.name', '아무개')
        ->assertJsonPath('meta.locale', 'vi');
});

it('근로자가 아닌 토큰(관리자 User)은 worker 미들웨어에서 차단된다', function () {
    $admin = User::factory()->create();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/me')->assertForbidden();
});

it('SOS 를 좌표와 함께 접수한다', function () {
    $worker = Worker::factory()->create();
    Sanctum::actingAs($worker);

    $this->postJson('/api/v1/sos', ['lat' => 37.5665, 'lng' => 126.9780])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');

    $alert = SosAlert::where('worker_id', $worker->id)->first();
    expect($alert)->not->toBeNull()
        ->and((float) $alert->lat)->toBe(37.5665)
        ->and((float) $alert->lng)->toBe(126.978);
});

it('SOS 는 좌표 없이도 접수된다', function () {
    $worker = Worker::factory()->create();
    Sanctum::actingAs($worker);

    $this->postJson('/api/v1/sos', [])->assertCreated();

    $alert = SosAlert::where('worker_id', $worker->id)->first();
    expect($alert->lat)->toBeNull()->and($alert->lng)->toBeNull();
});

it('SOS 좌표 범위 검증이 동작한다', function () {
    $worker = Worker::factory()->create();
    Sanctum::actingAs($worker);

    $this->postJson('/api/v1/sos', ['lat' => 999, 'lng' => 0])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('lat');
});

it('근로자가 온보딩 정보를 저장하고 제출한다', function () {
    $worker = Worker::factory()->create();
    Sanctum::actingAs($worker);

    // 저장 (draft 생성) — 새 리소스라 201 Created
    $this->postJson('/api/v1/onboarding', [
        'payload' => ['address_kr' => '부산시 해운대구'],
    ])->assertCreated()->assertJsonPath('data.status', 'draft');

    // 제출
    $this->postJson('/api/v1/onboarding/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    $sub = OnboardingSubmission::where('worker_id', $worker->id)->first();
    expect($sub->status)->toBe(OnboardingStatus::Submitted)
        ->and($sub->payload['address_kr'])->toBe('부산시 해운대구');
});

it('다른 근로자의 온보딩은 보이지 않는다 (본인 스코프)', function () {
    $other = Worker::factory()->create();
    OnboardingSubmission::factory()->submitted()->create(['worker_id' => $other->id]);

    $me = Worker::factory()->create();
    Sanctum::actingAs($me);

    // 내 온보딩은 없음 → data: null
    $this->getJson('/api/v1/onboarding')
        ->assertOk()
        ->assertJsonPath('data', null);
});
