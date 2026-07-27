<?php

declare(strict_types=1);

use App\Domains\Arrival\Enums\ArrivalDocument;
use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Matching\Actions\ConfirmPlacementAction;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * 입국한 근로자 관리 (업무흐름 §5).
 */
beforeEach(function () {
    foreach (UserRole::values() as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
    Sanctum::actingAs($this->admin);
});

it('배정을 확정하면 입국 기록이 함께 만들어진다', function () {
    $placement = Placement::factory()->create();

    app(ConfirmPlacementAction::class)
        ->execute($placement, $this->admin);

    expect($placement->refresh()->status)->toBe(PlacementStatus::Confirmed)
        ->and($placement->arrival)->not->toBeNull()
        ->and($placement->arrival->status)->toBe(ArrivalStatus::Scheduled);
});

it('배정을 두 번 확정해도 입국 기록이 중복 생성되지 않는다', function () {
    $placement = Placement::factory()->create();
    $action = app(ConfirmPlacementAction::class);

    $action->execute($placement, $this->admin);

    // 확정 상태에서 재확정은 막힌다
    expect(fn () => $action->execute($placement->refresh(), $this->admin))
        ->toThrow(RuntimeException::class);

    expect(ArrivalRecord::where('placement_id', $placement->id)->count())->toBe(1);
});

it('입국 목록을 조회한다', function () {
    ArrivalRecord::factory()->count(3)->create();

    $this->getJson('/api/v1/admin/arrivals')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.status', ArrivalStatus::Scheduled->value);
});

it('필수 서류가 없으면 도착 확인으로 진행할 수 없다', function () {
    $record = ArrivalRecord::factory()->create();

    $response = $this->postJson("/api/v1/admin/arrivals/{$record->id}/advance", [
        'status' => ArrivalStatus::Arrived->value,
    ])->assertStatus(422);

    expect($response->json('message'))->toContain('여권');
    expect($record->refresh()->status)->toBe(ArrivalStatus::Scheduled);
});

it('필수 서류를 확인하면 도착 확인으로 진행된다', function () {
    $record = ArrivalRecord::factory()->documentsReady()->create();

    $this->postJson("/api/v1/admin/arrivals/{$record->id}/advance", [
        'status' => ArrivalStatus::Arrived->value,
    ])->assertOk()->assertJsonPath('data.status', ArrivalStatus::Arrived->value);

    expect($record->refresh()->arrived_at)->not->toBeNull();
});

it('단계를 건너뛸 수 없다', function () {
    $record = ArrivalRecord::factory()->documentsReady()->create();

    // scheduled → handed_over 로 바로 점프 시도
    $this->postJson("/api/v1/admin/arrivals/{$record->id}/advance", [
        'status' => ArrivalStatus::HandedOver->value,
    ])->assertStatus(422);

    expect($record->refresh()->status)->toBe(ArrivalStatus::Scheduled);
});

it('도착 → 픽업 → 인계까지 순서대로 진행되며 시각이 기록된다', function () {
    $record = ArrivalRecord::factory()->documentsReady()->create();

    foreach ([ArrivalStatus::Arrived, ArrivalStatus::PickedUp, ArrivalStatus::HandedOver] as $stage) {
        $this->postJson("/api/v1/admin/arrivals/{$record->id}/advance", ['status' => $stage->value])
            ->assertOk()
            ->assertJsonPath('data.status', $stage->value);
    }

    $record->refresh();
    expect($record->status)->toBe(ArrivalStatus::HandedOver)
        ->and($record->arrived_at)->not->toBeNull()
        ->and($record->picked_up_at)->not->toBeNull()
        ->and($record->handed_over_at)->not->toBeNull();
});

it('항공편·서류 체크리스트를 수정한다', function () {
    $record = ArrivalRecord::factory()->create();

    $this->postJson("/api/v1/admin/arrivals/{$record->id}", [
        'flight_no' => 'VN409',
        'airport' => '인천(ICN)',
        'documents' => [ArrivalDocument::Passport->value => true],
    ])->assertOk()
        ->assertJsonPath('data.flight_no', 'VN409')
        ->assertJsonPath('data.documents.passport', true);

    // 알 수 없는 키는 무시된다
    $this->postJson("/api/v1/admin/arrivals/{$record->id}", [
        'documents' => ['bogus_key' => true],
    ])->assertOk();

    expect($record->refresh()->checklist())->not->toHaveKey('bogus_key');
});

it('입국 기록 테이블에는 위치 컬럼이 없다 (§7-2)', function () {
    $columns = Schema::getColumnListing('arrival_records');

    expect($columns)->not->toContain('lat')
        ->and($columns)->not->toContain('lng');
});
