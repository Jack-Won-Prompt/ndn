<?php

declare(strict_types=1);

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\SosStatus;
use App\Domains\Support\Models\SosAlert;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * SOS 상황판·대응 (업무흐름 §7·§8).
 */
beforeEach(function () {
    foreach (UserRole::values() as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
});

function makeAlert(SosStatus $status = SosStatus::Open, ?int $minutesAgo = 10): SosAlert
{
    return SosAlert::create([
        'worker_id' => Worker::factory()->create()->id,
        'lat' => 37.5665,
        'lng' => 126.9780,
        'alerted_at' => now()->subMinutes($minutesAgo),
        'status' => $status,
    ]);
}

it('미확인 SOS 가 목록 맨 위에 온다', function () {
    makeAlert(SosStatus::Closed, 60);
    makeAlert(SosStatus::Acknowledged, 30);
    $open = makeAlert(SosStatus::Open, 5);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/sos')
        ->assertOk()
        ->assertJsonPath('data.0.id', $open->id)
        ->assertJsonPath('meta.open_count', 1);
});

it('SOS 를 확인 처리하면 확인자와 시각이 남는다', function () {
    $alert = makeAlert();
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/sos/{$alert->id}/status", [
        'status' => SosStatus::Acknowledged->value,
        'note' => '전화 연결 완료',
    ])->assertOk()->assertJsonPath('data.status', SosStatus::Acknowledged->value);

    $alert->refresh();
    expect($alert->acknowledged_by)->toBe($this->admin->id)
        ->and($alert->acknowledged_at)->not->toBeNull()
        ->and($alert->note)->toBe('전화 연결 완료');
});

it('이미 종료된 SOS 는 다시 확인할 수 없다', function () {
    $alert = makeAlert(SosStatus::Closed);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/sos/{$alert->id}/status", [
        'status' => SosStatus::Acknowledged->value,
    ])->assertStatus(422);
});

it('SOS 응답에 좌표가 포함된다 (발신 순간 1회 좌표)', function () {
    makeAlert();
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/sos')
        ->assertOk()
        ->assertJsonPath('data.0.lat', 37.5665);
});

it('좌표 없이 접수된 SOS 도 목록에 보인다', function () {
    SosAlert::create([
        'worker_id' => Worker::factory()->create()->id,
        'alerted_at' => now(),
        'status' => SosStatus::Open,
    ]);
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/sos')
        ->assertOk()
        ->assertJsonPath('data.0.lat', null);
});

it('시청 담당자는 SOS 상태를 바꿀 수 없다', function () {
    $alert = makeAlert();

    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);
    Sanctum::actingAs($officer);

    $this->postJson("/api/v1/admin/sos/{$alert->id}/status", [
        'status' => SosStatus::Acknowledged->value,
    ])->assertForbidden();

    expect($alert->refresh()->status)->toBe(SosStatus::Open);
});
