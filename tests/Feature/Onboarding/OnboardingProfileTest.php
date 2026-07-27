<?php

declare(strict_types=1);

use App\Domains\Onboarding\Actions\ReviewOnboardingAction;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\Gender;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

/**
 * 온보딩 성별·생년월일 항목 (업무흐름 §3 → §4 매칭).
 *
 * 근로자가 적은 값은 payload 에만 남고, **검수 승인 시점에만** workers 컬럼으로
 * 승격된다. 매칭 조건 대조가 이 컬럼을 쓴다.
 */
function submitProfile(Worker $worker, array $payload): OnboardingSubmission
{
    Sanctum::actingAs($worker);

    test()->postJson('/api/v1/onboarding', ['payload' => $payload])->assertCreated();
    test()->postJson('/api/v1/onboarding/submit')->assertOk();

    return OnboardingSubmission::where('worker_id', $worker->id)->latest('id')->firstOrFail();
}

it('근로자가 성별·생년월일을 온보딩에 기입할 수 있다', function () {
    $worker = Worker::factory()->create(['gender' => null]);

    $submission = submitProfile($worker, [
        'gender' => 'female',
        'birth_date' => '1995-03-12',
        'address_kr' => '충남 당진시',
    ]);

    expect($submission->payload['gender'])->toBe('female')
        ->and($submission->payload['birth_date'])->toBe('1995-03-12');
});

it('승인 전에는 근로자 레코드가 바뀌지 않는다', function () {
    $worker = Worker::factory()->create(['gender' => null]);

    submitProfile($worker, ['gender' => 'female', 'birth_date' => '1995-03-12']);

    expect($worker->refresh()->gender)->toBeNull();
});

it('승인하면 성별·생년월일이 근로자 레코드로 반영된다', function () {
    $worker = Worker::factory()->create(['gender' => null, 'birth_date' => null]);
    $submission = submitProfile($worker, [
        'gender' => 'female',
        'birth_date' => '1995-03-12',
    ]);

    app(ReviewOnboardingAction::class)->execute(
        $submission,
        User::factory()->create(),
        OnboardingStatus::Approved,
    );

    $worker->refresh();
    expect($worker->gender)->toBe(Gender::Female)
        ->and((string) $worker->birth_date)->toBe('1995-03-12');
});

it('반려하면 근로자 레코드에 반영되지 않는다', function () {
    $worker = Worker::factory()->create(['gender' => null]);
    $submission = submitProfile($worker, ['gender' => 'male']);

    app(ReviewOnboardingAction::class)->execute(
        $submission,
        User::factory()->create(),
        OnboardingStatus::Rejected,
    );

    expect($worker->refresh()->gender)->toBeNull();
});

it('빈 값은 기존에 확인된 정보를 덮어쓰지 않는다', function () {
    $worker = Worker::factory()->create(['gender' => Gender::Male]);
    $submission = submitProfile($worker, ['address_kr' => '주소만 적음']);

    app(ReviewOnboardingAction::class)->execute(
        $submission,
        User::factory()->create(),
        OnboardingStatus::Approved,
    );

    expect($worker->refresh()->gender)->toBe(Gender::Male);
});

it("수요 조건용 '무관'은 근로자 성별이 될 수 없다", function () {
    $worker = Worker::factory()->create(['gender' => null]);
    $submission = OnboardingSubmission::factory()->submitted()->create([
        'worker_id' => $worker->id,
        'payload' => ['gender' => Gender::Any->value],
    ]);

    app(ReviewOnboardingAction::class)->execute(
        $submission,
        User::factory()->create(),
        OnboardingStatus::Approved,
    );

    expect($worker->refresh()->gender)->toBeNull();
});

it('규칙이 없는 payload 항목도 그대로 저장된다 (자유 형식 유실 금지)', function () {
    $worker = Worker::factory()->create();

    // 성별·생년월일에만 검증 규칙이 있다. validated() 를 그대로 쓰면 규칙 없는
    // 키가 잘려 나가므로, 주소·계좌 같은 자유 항목이 사라지지 않는지 확인한다.
    $submission = submitProfile($worker, [
        'gender' => 'male',
        'address_kr' => '충남 당진시 합덕읍',
        'bank_account' => '123-456-789',
        'emergency_name' => '홍길동',
    ]);

    expect($submission->payload)
        ->toHaveKey('address_kr', '충남 당진시 합덕읍')
        ->toHaveKey('bank_account', '123-456-789')
        ->toHaveKey('emergency_name', '홍길동')
        ->toHaveKey('gender', 'male');
});

it('허용되지 않은 성별 값은 저장 단계에서 막힌다', function () {
    Sanctum::actingAs(Worker::factory()->create());

    $this->postJson('/api/v1/onboarding', [
        'payload' => ['gender' => 'other'],
    ])->assertStatus(422)->assertJsonValidationErrorFor('payload.gender');
});

it('미래 날짜 생년월일은 막힌다', function () {
    Sanctum::actingAs(Worker::factory()->create());

    $this->postJson('/api/v1/onboarding', [
        'payload' => ['birth_date' => now()->addYear()->toDateString()],
    ])->assertStatus(422)->assertJsonValidationErrorFor('payload.birth_date');
});

it('승인 반영이 감사 로그에 남되 값 자체는 남지 않는다 (§7-1)', function () {
    $worker = Worker::factory()->create(['gender' => null]);
    $submission = submitProfile($worker, [
        'gender' => 'female',
        'birth_date' => '1995-03-12',
    ]);

    app(ReviewOnboardingAction::class)->execute(
        $submission,
        User::factory()->create(),
        OnboardingStatus::Approved,
    );

    $log = Activity::where('log_name', 'worker-profile')
        ->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['fields'])->toContain('gender')
        // 생년월일 원문이 로그에 들어가면 안 된다
        ->and(json_encode($log->properties))->not->toContain('1995-03-12');
});
