<?php

declare(strict_types=1);

use App\Domains\Onboarding\Actions\ReviewOnboardingAction;
use App\Domains\Onboarding\Actions\SubmitOnboardingAction;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('제출 Action 이 draft 를 submitted 로 바꾼다', function () {
    $s = OnboardingSubmission::factory()->create(['status' => OnboardingStatus::Draft]);

    app(SubmitOnboardingAction::class)->execute($s);

    expect($s->fresh()->status)->toBe(OnboardingStatus::Submitted)
        ->and($s->fresh()->submitted_at)->not->toBeNull();
});

it('본인 기입 payload 는 DB 에 암호문으로 저장된다', function () {
    $s = OnboardingSubmission::factory()->create([
        'payload' => ['address_kr' => '서울시 강남구 테헤란로 1'],
    ]);

    $raw = DB::table('onboarding_submissions')->where('id', $s->id)->value('payload');

    expect($raw)->not->toContain('테헤란로')
        ->and($raw)->toStartWith('eyJ');

    // 모델로 읽으면 복호화되어 배열로 나온다
    expect($s->fresh()->payload['address_kr'])->toBe('서울시 강남구 테헤란로 1');
});

it('검수 Action 이 submitted 를 approved 로 만든다', function () {
    $admin = User::factory()->create();
    $s = OnboardingSubmission::factory()->submitted()->create();

    app(ReviewOnboardingAction::class)->execute($s, $admin, OnboardingStatus::Approved, '이상 없음');

    $fresh = $s->fresh();
    expect($fresh->status)->toBe(OnboardingStatus::Approved)
        ->and($fresh->reviewed_by)->toBe($admin->id)
        ->and($fresh->review_note)->toBe('이상 없음');
});

it('반려 후 재제출이 가능하다', function () {
    $admin = User::factory()->create();
    $s = OnboardingSubmission::factory()->submitted()->create();

    app(ReviewOnboardingAction::class)->execute($s, $admin, OnboardingStatus::Rejected, '서류 누락');
    expect($s->fresh()->status)->toBe(OnboardingStatus::Rejected);

    app(SubmitOnboardingAction::class)->execute($s->fresh());
    expect($s->fresh()->status)->toBe(OnboardingStatus::Submitted)
        ->and($s->fresh()->review_note)->toBeNull(); // 재제출 시 이전 검수 초기화
});

it('승인 결정이 아닌 상태로는 검수할 수 없다', function () {
    $admin = User::factory()->create();
    $s = OnboardingSubmission::factory()->submitted()->create();

    app(ReviewOnboardingAction::class)->execute($s, $admin, OnboardingStatus::Draft);
})->throws(InvalidArgumentException::class);
