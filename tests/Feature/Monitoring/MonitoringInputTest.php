<?php

declare(strict_types=1);

use App\Domains\Monitoring\Enums\InterviewSource;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function monitoringAdmin(): User
{
    $u = User::factory()->create();
    $u->assignRole(UserRole::NdnAdmin->value);

    return $u;
}

it('본사가 월별 점검을 직접 입력하면 점검자(inspector) 기록으로 저장되고 리스크가 산정된다', function () {
    $worker = Worker::factory()->create();
    actingAs(monitoringAdmin());

    post(route('admin.monitoring.store'), [
        'worker_id' => $worker->id,
        'interviewed_on' => '2026-07-27',
        'items' => ['pay_received' => 0, 'no_flight_signs' => 0, 'no_discrimination' => 1, 'follows_rules' => 1, 'adapts_group' => 1, 'health_ok' => 1],
        'memo' => '급여 지연 확인',
    ])->assertOk()->assertJsonPath('ok', true);

    $iv = MonthlyInterview::where('worker_id', $worker->id)->first();
    expect($iv)->not->toBeNull();
    expect($iv->source)->toBe(InterviewSource::Inspector);
    expect($iv->farm_visit_id)->toBeNull();
    expect($iv->pay_received)->toBeFalse();
    expect($iv->risk_level)->toBe(RiskLevel::Medium);   // 부정 2개
});

it('근로자 미선택이면 점검 입력에 실패한다', function () {
    actingAs(monitoringAdmin());

    post(route('admin.monitoring.store'), ['interviewed_on' => '2026-07-27'], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('일반 사용자는 월별 점검을 직접 입력할 수 없다', function () {
    $worker = Worker::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(UserRole::FarmOwner->value);
    actingAs($user);

    post(route('admin.monitoring.store'), [
        'worker_id' => $worker->id, 'interviewed_on' => '2026-07-27',
    ])->assertForbidden();
});
