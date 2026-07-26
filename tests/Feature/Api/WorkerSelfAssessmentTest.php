<?php

declare(strict_types=1);

use App\Domains\Monitoring\Enums\InterviewSource;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use Laravel\Sanctum\Sanctum;

/**
 * 근로자 앱 — 근로 생활 평가 (업무흐름 §7).
 */
$allGood = [
    'pay_received' => true,
    'no_discrimination' => true,
    'follows_rules' => true,
    'adapts_group' => true,
    'health_ok' => true,
    'no_flight_signs' => true,
];

it('인증 없이는 평가 API 에 접근할 수 없다', function () {
    $this->getJson('/api/v1/interviews')->assertUnauthorized();
});

it('자가 평가를 제출하면 리스크가 산출되어 저장된다', function () use ($allGood) {
    $worker = Worker::factory()->create();
    Sanctum::actingAs($worker);

    $this->postJson('/api/v1/interviews', [
        ...$allGood,
        'pay_received' => false,      // 부정 신호 1
        'health_ok' => false,         // 부정 신호 2
        'memo' => '급여가 늦어요',
    ])->assertCreated()
        ->assertJsonPath('data.risk_score', 2)
        ->assertJsonPath('data.risk_level', RiskLevel::Medium->value)
        ->assertJsonPath('data.source', InterviewSource::Self->value)
        ->assertJsonPath('data.items.pay_received', false);

    $row = MonthlyInterview::where('worker_id', $worker->id)->first();
    expect($row->source)->toBe(InterviewSource::Self)
        ->and($row->inspector_user_id)->toBeNull()
        ->and($row->risk_score)->toBe(2);
});

it('부정 신호 3개 이상이면 고위험으로 분류된다', function () use ($allGood) {
    Sanctum::actingAs(Worker::factory()->create());

    $this->postJson('/api/v1/interviews', [
        ...$allGood,
        'pay_received' => false,
        'no_discrimination' => false,
        'no_flight_signs' => false,
    ])->assertCreated()
        ->assertJsonPath('data.risk_score', 3)
        ->assertJsonPath('data.risk_level', RiskLevel::High->value);
});

it('6개 항목은 모두 필수다', function () {
    Sanctum::actingAs(Worker::factory()->create());

    $this->postJson('/api/v1/interviews', ['pay_received' => true])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('health_ok');
});

it('같은 달에 다시 제출하면 새 행이 아니라 기존 행을 갱신한다', function () use ($allGood) {
    $worker = Worker::factory()->create();
    Sanctum::actingAs($worker);

    // 첫 제출은 생성(201), 같은 달 재제출은 기존 행 갱신(200)
    $this->postJson('/api/v1/interviews', $allGood)->assertCreated();
    $this->postJson('/api/v1/interviews', [...$allGood, 'health_ok' => false])->assertOk();

    $rows = MonthlyInterview::where('worker_id', $worker->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->health_ok)->toBeFalse()
        ->and($rows->first()->risk_score)->toBe(1);
});

it('본인 평가 이력만 조회된다 (다른 근로자 것은 제외)', function () use ($allGood) {
    $other = Worker::factory()->create();
    MonthlyInterview::factory()->create(['worker_id' => $other->id]);

    $me = Worker::factory()->create();
    Sanctum::actingAs($me);
    $this->postJson('/api/v1/interviews', $allGood)->assertCreated();

    $response = $this->getJson('/api/v1/interviews')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.self_submitted_this_month', true);

    expect($response->json('data.0.source'))->toBe(InterviewSource::Self->value);
});

it('점검자 방문 기록도 본인 이력에 함께 보인다', function () {
    $worker = Worker::factory()->create();
    MonthlyInterview::factory()->create([
        'worker_id' => $worker->id,
        'source' => InterviewSource::Inspector,
    ]);
    Sanctum::actingAs($worker);

    $this->getJson('/api/v1/interviews')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.source', InterviewSource::Inspector->value)
        ->assertJsonPath('meta.self_submitted_this_month', false);
});

it('평가 응답에 점검자 신원이 노출되지 않는다', function () {
    $worker = Worker::factory()->create();
    MonthlyInterview::factory()->create(['worker_id' => $worker->id]);
    Sanctum::actingAs($worker);

    $row = $this->getJson('/api/v1/interviews')->assertOk()->json('data.0');

    expect($row)->not->toHaveKey('inspector_user_id')
        ->and($row)->not->toHaveKey('inspector');
});
