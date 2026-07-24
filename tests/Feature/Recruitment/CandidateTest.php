<?php

declare(strict_types=1);

use App\Domains\Recruitment\Actions\EvaluateCandidateAction;
use App\Domains\Recruitment\Actions\PromoteFromWaitlistAction;
use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Models\User;

it('총점 70 이상이면 합격', function () {
    $c = Candidate::factory()->create();
    app(EvaluateCandidateAction::class)->execute($c, User::factory()->create(), ['a' => 40, 'b' => 35]);

    expect($c->fresh()->status)->toBe(CandidateStatus::Passed)
        ->and($c->fresh()->queue_position)->toBeNull();
});

it('총점 50~69면 보류이며 대기열 순번이 부여된다', function () {
    $c = Candidate::factory()->create();
    app(EvaluateCandidateAction::class)->execute($c, User::factory()->create(), ['a' => 30, 'b' => 25]);

    expect($c->fresh()->status)->toBe(CandidateStatus::Held)
        ->and($c->fresh()->queue_position)->toBe(1);
});

it('총점 50 미만이면 불합격', function () {
    $c = Candidate::factory()->create();
    app(EvaluateCandidateAction::class)->execute($c, User::factory()->create(), ['a' => 20, 'b' => 20]);

    expect($c->fresh()->status)->toBe(CandidateStatus::Rejected);
});

it('보류자는 순번대로 대기열에 쌓인다', function () {
    $interviewer = User::factory()->create();
    $c1 = Candidate::factory()->create();
    $c2 = Candidate::factory()->create();

    app(EvaluateCandidateAction::class)->execute($c1, $interviewer, ['x' => 55]);
    app(EvaluateCandidateAction::class)->execute($c2, $interviewer, ['x' => 55]);

    expect($c1->fresh()->queue_position)->toBe(1)
        ->and($c2->fresh()->queue_position)->toBe(2);
});

it('결원 충원 시 대기열 최선순위 보류자가 합격으로 승격된다', function () {
    Candidate::factory()->held(1)->create(['name' => '1순위']);
    Candidate::factory()->held(2)->create(['name' => '2순위']);

    $promoted = app(PromoteFromWaitlistAction::class)->execute();

    expect($promoted->name)->toBe('1순위')
        ->and($promoted->status)->toBe(CandidateStatus::Passed)
        ->and($promoted->queue_position)->toBeNull();
});

it('합격·보류자만 온보딩 초대 대상이다', function () {
    expect(CandidateStatus::Passed->isOnboardingEligible())->toBeTrue()
        ->and(CandidateStatus::Held->isOnboardingEligible())->toBeTrue()
        ->and(CandidateStatus::Rejected->isOnboardingEligible())->toBeFalse();
});
