<?php

declare(strict_types=1);

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\Gender;
use App\Shared\Enums\UserRole;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * 매칭 — 조건 대조 추천 + 배정 (업무흐름 §4).
 */
beforeEach(function () {
    foreach (UserRole::values() as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
    Sanctum::actingAs($this->admin);

    $this->farm = Farm::factory()->create();
});

function makeDemand(array $attributes = []): DemandRequest
{
    return DemandRequest::factory()->create([
        'farm_id' => test()->farm->id,
        'nationality' => 'VN',
        'headcount' => 3,
        'status' => DemandStatus::Submitted,
        'gender' => Gender::Any,
        'age_min' => null,
        'age_max' => null,
        'allow_siblings' => false,
        ...$attributes,
    ]);
}

function makeWorker(array $attributes = []): Worker
{
    return Worker::factory()->create([
        'nationality' => 'VN',
        'status' => WorkerStatus::Active,
        ...$attributes,
    ]);
}

it('국적이 다른 근로자는 후보에 나오지 않는다', function () {
    $demand = makeDemand();
    $match = makeWorker(['name' => '베트남']);
    makeWorker(['nationality' => 'BD', 'name' => '방글']);

    $ids = collect(
        $this->getJson("/api/v1/admin/demands/{$demand->id}/candidates")
            ->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->toContain($match->id)->toHaveCount(1);
});

it('이미 배정된 근로자는 후보에서 빠진다', function () {
    $demand = makeDemand();
    $free = makeWorker();
    $taken = makeWorker();
    Placement::factory()->confirmed()->create([
        'worker_id' => $taken->id,
        'farm_id' => Farm::factory()->create()->id,
    ]);

    $ids = collect(
        $this->getJson("/api/v1/admin/demands/{$demand->id}/candidates")
            ->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->toContain($free->id)->not->toContain($taken->id);
});

it('취소된 배정의 근로자는 다시 후보가 된다', function () {
    $demand = makeDemand();
    $worker = makeWorker();
    Placement::factory()->create([
        'worker_id' => $worker->id,
        'farm_id' => Farm::factory()->create()->id,
        'status' => PlacementStatus::Cancelled,
    ]);

    $ids = collect(
        $this->getJson("/api/v1/admin/demands/{$demand->id}/candidates")
            ->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->toContain($worker->id);
});

it('성별 조건이 대조 결과에 반영된다', function () {
    $demand = makeDemand(['gender' => Gender::Female]);
    $female = makeWorker(['gender' => Gender::Female]);
    $male = makeWorker(['gender' => Gender::Male]);

    $rows = collect(
        $this->getJson("/api/v1/admin/demands/{$demand->id}/candidates")
            ->assertOk()->json('data')
    )->keyBy('id');

    expect($rows[$female->id]['matches']['gender'])->toBeTrue()
        ->and($rows[$male->id]['matches']['gender'])->toBeFalse();
});

it('나이 범위가 대조 결과에 반영된다 (암호화 필드라 PHP 계산)', function () {
    $demand = makeDemand(['age_min' => 25, 'age_max' => 35]);
    $inRange = makeWorker(['birth_date' => now()->subYears(30)->toDateString()]);
    $tooYoung = makeWorker(['birth_date' => now()->subYears(20)->toDateString()]);

    $rows = collect(
        $this->getJson("/api/v1/admin/demands/{$demand->id}/candidates")
            ->assertOk()->json('data')
    )->keyBy('id');

    expect($rows[$inRange->id]['matches']['age'])->toBeTrue()
        ->and($rows[$tooYoung->id]['matches']['age'])->toBeFalse();
});

it('정보가 없으면 제외하지 않고 판단 불가(null)로 표시한다', function () {
    $demand = makeDemand(['gender' => Gender::Female, 'age_min' => 25]);
    $unknown = makeWorker(['gender' => null, 'birth_date' => null]);

    $rows = collect(
        $this->getJson("/api/v1/admin/demands/{$demand->id}/candidates")
            ->assertOk()->json('data')
    )->keyBy('id');

    // 후보에는 남아 있고, 항목은 null
    expect($rows)->toHaveKey($unknown->id)
        ->and($rows[$unknown->id]['matches']['gender'])->toBeNull()
        ->and($rows[$unknown->id]['matches']['age'])->toBeNull();
});

it('조건을 더 많이 만족한 후보가 위에 온다', function () {
    $demand = makeDemand(['gender' => Gender::Female, 'age_min' => 25, 'age_max' => 35]);
    makeWorker(['gender' => Gender::Male, 'birth_date' => now()->subYears(20)->toDateString()]);
    $best = makeWorker([
        'gender' => Gender::Female,
        'birth_date' => now()->subYears(30)->toDateString(),
    ]);

    $data = $this->getJson("/api/v1/admin/demands/{$demand->id}/candidates")
        ->assertOk()->json('data');

    expect($data[0]['id'])->toBe($best->id)
        ->and($data[0]['score'])->toBe(3);
});

it('배정을 만들면 제안 상태가 된다', function () {
    $demand = makeDemand();
    $worker = makeWorker();

    $this->postJson('/api/v1/admin/placements', [
        'demand_id' => $demand->id,
        'worker_ids' => [$worker->id],
    ])->assertCreated()
        ->assertJsonPath('data.0.status', PlacementStatus::Proposed->value);
});

it('이미 배정된 근로자는 다시 배정할 수 없다', function () {
    $demand = makeDemand();
    $worker = makeWorker();

    $this->postJson('/api/v1/admin/placements', [
        'demand_id' => $demand->id, 'worker_ids' => [$worker->id],
    ])->assertCreated();

    $this->postJson('/api/v1/admin/placements', [
        'demand_id' => $demand->id, 'worker_ids' => [$worker->id],
    ])->assertStatus(422);

    expect(Placement::where('worker_id', $worker->id)->count())->toBe(1);
});

it('요청 인원을 초과해 배정할 수 없다', function () {
    $demand = makeDemand(['headcount' => 2]);
    $workers = collect(range(1, 3))->map(fn () => makeWorker());

    $response = $this->postJson('/api/v1/admin/placements', [
        'demand_id' => $demand->id,
        'worker_ids' => $workers->pluck('id')->all(),
    ])->assertStatus(422);

    expect($response->json('message'))->toContain('초과');
    expect(Placement::count())->toBe(0);
});

it('형제 동반을 허용한 수요는 그룹으로 배정된다', function () {
    $demand = makeDemand(['allow_siblings' => true]);
    $a = makeWorker();
    $b = makeWorker();

    $data = $this->postJson('/api/v1/admin/placements', [
        'demand_id' => $demand->id,
        'worker_ids' => [$a->id, $b->id],
        'as_group' => true,
    ])->assertCreated()->json('data');

    // 두 건이 같은 그룹 id 를 공유한다
    expect($data[0]['group_id'])->not->toBeNull()
        ->and($data[0]['group_id'])->toBe($data[1]['group_id']);
});

it('형제 동반을 허용하지 않은 수요에는 그룹 배정을 만들 수 없다', function () {
    $demand = makeDemand(['allow_siblings' => false]);
    $a = makeWorker();

    $this->postJson('/api/v1/admin/placements', [
        'demand_id' => $demand->id,
        'worker_ids' => [$a->id],
        'as_group' => true,
    ])->assertStatus(422);
});

it('배정을 확정하면 입국 기록이 생긴다', function () {
    $demand = makeDemand();
    $worker = makeWorker();

    $id = $this->postJson('/api/v1/admin/placements', [
        'demand_id' => $demand->id, 'worker_ids' => [$worker->id],
    ])->json('data.0.id');

    $this->postJson("/api/v1/admin/placements/{$id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', PlacementStatus::Confirmed->value);

    expect(Placement::find($id)->arrival)->not->toBeNull();
});

it('배정을 취소하면 사유가 남고 근로자가 다시 후보가 된다', function () {
    $demand = makeDemand();
    $worker = makeWorker();

    $id = $this->postJson('/api/v1/admin/placements', [
        'demand_id' => $demand->id, 'worker_ids' => [$worker->id],
    ])->json('data.0.id');

    $this->postJson("/api/v1/admin/placements/{$id}/cancel", ['reason' => '본인 사정'])
        ->assertOk()
        ->assertJsonPath('data.status', PlacementStatus::Cancelled->value)
        ->assertJsonPath('data.note', '본인 사정');

    $ids = collect(
        $this->getJson("/api/v1/admin/demands/{$demand->id}/candidates")->json('data')
    )->pluck('id');

    expect($ids)->toContain($worker->id);
});

it('시청·농가는 배정을 만들 수 없다', function () {
    $demand = makeDemand();
    $worker = makeWorker();

    $officer = User::factory()->create(['city_id' => $this->farm->city_id]);
    $officer->assignRole(UserRole::CityOfficer->value);
    Sanctum::actingAs($officer);

    $this->postJson('/api/v1/admin/placements', [
        'demand_id' => $demand->id, 'worker_ids' => [$worker->id],
    ])->assertForbidden();

    expect(Placement::count())->toBe(0);
});

it('다른 지자체의 수요는 조회할 수 없다', function () {
    $demand = makeDemand();

    $officer = User::factory()->create([
        'city_id' => City::factory()->create()->id,
    ]);
    $officer->assignRole(UserRole::CityOfficer->value);
    Sanctum::actingAs($officer);

    $this->getJson("/api/v1/admin/demands/{$demand->id}/candidates")->assertNotFound();
});
