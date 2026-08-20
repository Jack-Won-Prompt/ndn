<?php

declare(strict_types=1);

use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Admin\MatchingController;
use App\Http\Controllers\Admin\RegionController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * 기준정보(농가·지자체)를 지우면 매달린 화면도 함께 정리된다.
 *
 * 이 검사가 필요한 이유는 실제로 어긋났기 때문이다. 농가는 soft delete 인데
 * 배정은 아니어서 DB 의 cascadeOnDelete 가 돌지 않았고, 지운 농가에 배정 14건·
 * 수요 7건·방문점검 5건이 매달린 채 남았다. 그중 12명은 **없는 농가에 배정된
 * 상태**로 묶여 다른 농가에 넣을 수 없었다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);

    $this->farm = Farm::factory()->create(['name' => '지울농원']);
});

function deleteFarms(array $ids): TestResponse
{
    return actingAs(test()->admin)->postJson(route('admin.grid.farms.save'), [
        'deleted' => array_map(fn (int $id) => ['id' => $id], $ids),
    ]);
}

it('농가를 지우면 배정도 함께 접힌다', function () {
    $placement = Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => PlacementStatus::Confirmed,
    ]);

    deleteFarms([$this->farm->id])->assertOk();

    expect(Placement::find($placement->id))->toBeNull()
        // 지우기만 하고 없애지는 않는다 — 누가 어디에 있었는지가 증빙으로 남아야 한다.
        ->and(Placement::withTrashed()->find($placement->id))->not->toBeNull();
});

it('배정돼 있던 근로자는 미배정으로 풀린다', function () {
    // 이게 안 되면 그 사람은 없는 농가에 묶여 영영 다른 곳에 못 간다.
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active->value]);
    Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'worker_id' => $worker->id,
        'status' => PlacementStatus::Proposed,
    ]);

    expect(Worker::unassigned()->pluck('id'))->not->toContain($worker->id);

    deleteFarms([$this->farm->id])->assertOk();

    expect(Worker::unassigned()->pluck('id'))->toContain($worker->id);
});

it('왜 풀렸는지가 배정에 남는다', function () {
    // "이 사람 왜 빠졌지" 에 답할 수 있어야 한다 (업무흐름 §4).
    $placement = Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => PlacementStatus::Confirmed,
    ]);

    deleteFarms([$this->farm->id])->assertOk();

    $gone = Placement::withTrashed()->findOrFail($placement->id);

    expect($gone->status)->toBe(PlacementStatus::Cancelled)
        ->and($gone->note)->toContain('지울농원');

    expect(Activity::where('log_name', 'placement')->where('description', '배정 취소')->exists())
        ->toBeTrue();
});

it('수요·입국 기록·방문 점검도 함께 접힌다', function () {
    $demand = DemandRequest::factory()->create(['farm_id' => $this->farm->id]);
    $visit = FarmVisit::factory()->create(['farm_id' => $this->farm->id]);
    $placement = Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => PlacementStatus::Confirmed,
    ]);
    $arrival = ArrivalRecord::factory()->create(['placement_id' => $placement->id]);

    deleteFarms([$this->farm->id])->assertOk();

    expect(DemandRequest::find($demand->id))->toBeNull()
        ->and(FarmVisit::find($visit->id))->toBeNull()
        ->and(ArrivalRecord::find($arrival->id))->toBeNull();
});

it('다른 농가의 자료는 건드리지 않는다', function () {
    $keep = Farm::factory()->create(['name' => '남길농원']);
    $keepPlacement = Placement::factory()->create([
        'farm_id' => $keep->id,
        'status' => PlacementStatus::Confirmed,
    ]);
    Placement::factory()->create(['farm_id' => $this->farm->id]);

    deleteFarms([$this->farm->id])->assertOk();

    expect(Placement::find($keepPlacement->id))->not->toBeNull()
        ->and(Placement::find($keepPlacement->id)->status)->toBe(PlacementStatus::Confirmed)
        ->and(Farm::find($keep->id))->not->toBeNull();
});

it('무엇이 함께 정리됐는지 화면에 알려 준다', function () {
    // 농가 한 줄 지운 것이 어디까지 번졌는지 그 자리에서 보이지 않으면 알 길이 없다.
    DemandRequest::factory()->create(['farm_id' => $this->farm->id]);
    Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => PlacementStatus::Confirmed,
    ]);

    $message = deleteFarms([$this->farm->id])->assertOk()->json('message');

    expect($message)->toContain('농가 1곳 삭제')
        ->and($message)->toContain('배정')
        ->and($message)->toContain('수요');
});

it('지운 뒤에는 어느 화면에도 고아가 남지 않는다', function () {
    // 화면마다 whereHas 를 붙이는 대신, 애초에 고아가 생기지 않게 막는다.
    // 다음에 화면이 하나 더 늘어도 이 검사는 그대로 유효하다.
    Placement::factory()->count(3)->create(['farm_id' => $this->farm->id]);
    DemandRequest::factory()->count(2)->create(['farm_id' => $this->farm->id]);
    FarmVisit::factory()->create(['farm_id' => $this->farm->id]);

    deleteFarms([$this->farm->id])->assertOk();

    $orphans = [
        'placements' => DB::table('placements')->join('farms', 'farms.id', '=', 'placements.farm_id')
            ->whereNull('placements.deleted_at')->whereNotNull('farms.deleted_at')->count(),
        'demand_requests' => DB::table('demand_requests')->join('farms', 'farms.id', '=', 'demand_requests.farm_id')
            ->whereNull('demand_requests.deleted_at')->whereNotNull('farms.deleted_at')->count(),
        'farm_visits' => DB::table('farm_visits')->join('farms', 'farms.id', '=', 'farm_visits.farm_id')
            ->whereNull('farm_visits.deleted_at')->whereNotNull('farms.deleted_at')->count(),
    ];

    expect($orphans)->toBe(['placements' => 0, 'demand_requests' => 0, 'farm_visits' => 0]);
});

it('지운 농가는 목록에서 사라진다', function () {
    Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => PlacementStatus::Confirmed,
    ]);

    deleteFarms([$this->farm->id])->assertOk();

    expect(collect(MatchingController::farmRows())->pluck('id'))
        ->not->toContain($this->farm->id)
        ->and(MatchingController::placementRows())->toBe([])
        ->and(collect(MatchingController::rows())->pluck('farm_id'))
        ->not->toContain($this->farm->id);
});

it('지역별 배치 인원에도 지운 농가는 세지 않는다', function () {
    // 여기는 farms 를 직접 조인해서 Eloquent 의 걸림망이 지나가지 않는 자리다.
    $city = City::factory()->create();
    $farm = Farm::factory()->create(['city_id' => $city->id]);
    Placement::factory()->create(['farm_id' => $farm->id, 'status' => PlacementStatus::Confirmed]);

    expect(collect(RegionController::rows())->firstWhere('id', $city->id)['placed'])
        ->toBe(1);

    deleteFarms([$farm->id])->assertOk();

    expect(collect(RegionController::rows())->firstWhere('id', $city->id)['placed'])
        ->toBe(0);
});

it('아직 쓰이는 지자체는 지우지 못한다', function () {
    // nullOnDelete 라 그냥 지우면 농가의 지자체와 근로자의 지원 지역이 말없이 빈칸이 된다.
    $city = City::factory()->create(['name' => '쓰는시']);
    Farm::factory()->create(['city_id' => $city->id]);

    $res = actingAs($this->admin)->postJson(route('admin.grid.cities.save'), [
        'deleted' => [['id' => $city->id]],
    ])->assertStatus(422);

    expect($res->json('message'))->toContain('쓰는시')
        ->and($res->json('message'))->toContain('농가');

    expect(City::find($city->id))->not->toBeNull();
});

it('아무 데도 안 쓰이는 지자체는 지울 수 있다', function () {
    $city = City::factory()->create();

    actingAs($this->admin)->postJson(route('admin.grid.cities.save'), [
        'deleted' => [['id' => $city->id]],
    ])->assertOk();

    expect(City::find($city->id))->toBeNull();
});

it('정리 명령은 붙이지 않으면 보여 주기만 한다', function () {
    // 운영 데이터를 건드리는 명령이라 실수로 돌면 안 된다 (CLAUDE.md §11).
    $placement = Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => PlacementStatus::Confirmed,
    ]);
    $this->farm->delete();   // 예전 방식으로 농가만 지워진 상태를 만든다

    $this->artisan('farms:sweep-orphans')
        ->expectsOutputToContain('보여 주기만 했습니다.')
        ->assertSuccessful();

    expect(Placement::find($placement->id))->not->toBeNull();
});

it('정리 명령이 예전에 남은 고아를 걷어낸다', function () {
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active->value]);
    $placement = Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'worker_id' => $worker->id,
        'status' => PlacementStatus::Confirmed,
    ]);
    $demand = DemandRequest::factory()->create(['farm_id' => $this->farm->id]);
    $this->farm->delete();

    $this->artisan('farms:sweep-orphans --apply')->assertSuccessful();

    expect(Placement::find($placement->id))->toBeNull()
        ->and(DemandRequest::find($demand->id))->toBeNull()
        ->and(Placement::withTrashed()->find($placement->id)->status)->toBe(PlacementStatus::Cancelled)
        // 묶여 있던 사람이 풀려야 이 정리가 뜻이 있다.
        ->and(Worker::unassigned()->pluck('id'))->toContain($worker->id);
});

it('관리자가 아니면 농가를 지울 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    actingAs($officer)->postJson(route('admin.grid.farms.save'), [
        'deleted' => [['id' => $this->farm->id]],
    ])->assertForbidden();

    expect(Farm::find($this->farm->id))->not->toBeNull();
});
