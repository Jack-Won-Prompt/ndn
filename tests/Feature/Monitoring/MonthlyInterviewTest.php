<?php

declare(strict_types=1);

use App\Domains\Monitoring\Actions\RecordMonthlyInterviewAction;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('모든 항목 양호면 리스크 낮음, 스코어 0', function () {
    $worker = Worker::factory()->create();
    $inspector = User::factory()->create();

    $iv = app(RecordMonthlyInterviewAction::class)->execute(
        $worker, $inspector, '2026-07-01', [
            'pay_received' => true, 'no_discrimination' => true, 'follows_rules' => true,
            'adapts_group' => true, 'health_ok' => true, 'no_flight_signs' => true,
        ]
    );

    expect($iv->risk_score)->toBe(0)
        ->and($iv->risk_level)->toBe(RiskLevel::Low);
});

it('부정 1~2개면 주의', function () {
    $iv = app(RecordMonthlyInterviewAction::class)->execute(
        Worker::factory()->create(), User::factory()->create(), '2026-07-01',
        ['pay_received' => false, 'health_ok' => false]
    );

    expect($iv->risk_score)->toBe(2)
        ->and($iv->risk_level)->toBe(RiskLevel::Medium);
});

it('부정 3개 이상이면 고위험', function () {
    $iv = app(RecordMonthlyInterviewAction::class)->execute(
        Worker::factory()->create(), User::factory()->create(), '2026-07-01',
        ['pay_received' => false, 'no_flight_signs' => false, 'adapts_group' => false, 'health_ok' => false]
    );

    expect($iv->risk_score)->toBe(4)
        ->and($iv->risk_level)->toBe(RiskLevel::High);
});

it('월별 인터뷰 테이블에는 위치 컬럼이 없다 (§7-2)', function () {
    $cols = Schema::getColumnListing('monthly_interviews');
    expect($cols)->not->toContain('lat')->and($cols)->not->toContain('lng');
})->group('guard');
