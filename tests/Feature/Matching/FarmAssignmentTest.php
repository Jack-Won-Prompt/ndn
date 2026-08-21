<?php

declare(strict_types=1);

use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Admin\MatchingController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * 농가에서 출발하는 배정 (매칭 화면 '농가별 배정' 탭).
 *
 * 본사는 농가를 받아 적은 뒤 곧바로 사람을 붙인다. 그 한 줄기를 한 화면에서
 * 끝낼 수 있어야 하고, 그러려면 농가 등록 → 수요 → 배정이 끊기지 않아야 한다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);

    $this->city = City::factory()->create(['name' => '테스트시']);
    $this->farm = Farm::factory()->create(['name' => '배정테스트농원', 'city_id' => $this->city->id]);
});

it('농가 목록에 수요·배정 숫자가 함께 온다', function () {
    $demand = DemandRequest::factory()->create([
        'farm_id' => $this->farm->id,
        'headcount' => 5,
        'status' => DemandStatus::Submitted,
    ]);
    Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => PlacementStatus::Proposed,
    ]);

    $rows = MatchingController::farmRows();
    $row = collect($rows)->firstWhere('id', $this->farm->id);

    expect($row['demands'])->toBe(1)
        ->and($row['need'])->toBe(5)
        ->and($row['placed'])->toBe(1)
        // 기준정보와 같은 칸도 함께 와야 그 표에서 바로 고칠 수 있다.
        ->and($row)->toHaveKeys(['name', 'city_id', 'address', 'business_reg_no']);

    expect($demand->fresh())->not->toBeNull();
});

it('취소된 배정은 채워진 인원으로 세지 않는다', function () {
    // 취소했는데도 자리를 차지하고 있으면 그 농가는 영영 정원이 차 있다.
    Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => PlacementStatus::Cancelled,
    ]);

    $row = collect(MatchingController::farmRows())
        ->firstWhere('id', $this->farm->id);

    expect($row['placed'])->toBe(0);
});

it('농가를 열면 그 농가의 수요와 배정만 나온다', function () {
    $mine = DemandRequest::factory()->create(['farm_id' => $this->farm->id]);
    $other = DemandRequest::factory()->create();

    $json = actingAs($this->admin)
        ->getJson(route('admin.matching.farm', $this->farm))
        ->assertOk()->json();

    expect($json['farm']['name'])->toBe('배정테스트농원')
        ->and($json['farm']['city'])->toBe('테스트시')
        ->and(collect($json['demands'])->pluck('id')->all())->toBe([$mine->id])
        ->and(collect($json['demands'])->pluck('id')->all())->not->toContain($other->id);
});

it('농가에 수요가 없으면 빈 목록으로 알려 준다', function () {
    // 화면은 이걸 보고 '수요를 먼저 등록하라' 고 안내한다.
    $json = actingAs($this->admin)
        ->getJson(route('admin.matching.farm', $this->farm))
        ->assertOk()->json();

    expect($json['demands'])->toBe([])
        ->and($json['placements'])->toBe([]);
});

it('농가 화면에서 수요를 등록하면 바로 제출됨이 된다', function () {
    // 본사가 콘솔에서 직접 적어 넣는 수요는 농가가 작성 중인 초안이 아니다.
    // 제출됨이라야 [수요별 매칭] 목록에도 함께 나온다.
    $json = actingAs($this->admin)->postJson(route('admin.matching.demand.store', $this->farm), [
        'nationality' => 'VN',
        'headcount' => 3,
        'gender' => 'any',
        'crop' => '딸기',
        'period_start' => '2027-03-01',
        'period_end' => '2027-06-30',
        'allow_siblings' => true,
    ])->assertOk()->json();

    $demand = DemandRequest::findOrFail($json['demand_id']);

    expect($demand->status)->toBe(DemandStatus::Submitted)
        ->and($demand->farm_id)->toBe($this->farm->id)
        // 지자체를 따로 적지 않아도 농가의 지자체를 물려받는다.
        ->and($demand->city_id)->toBe($this->city->id)
        ->and($demand->allow_siblings)->toBeTrue();

    expect(collect(MatchingController::rows())->pluck('id'))
        ->toContain($demand->id);
});

it('기간이 거꾸로면 수요를 만들지 않는다', function () {
    actingAs($this->admin)->postJson(route('admin.matching.demand.store', $this->farm), [
        'nationality' => 'VN',
        'headcount' => 1,
        'gender' => 'any',
        'crop' => '딸기',
        'period_start' => '2027-06-30',
        'period_end' => '2027-03-01',
    ])->assertStatus(422)->assertJsonValidationErrors('period_end');

    expect(DemandRequest::count())->toBe(0);
});

it('농가 등록부터 배정까지 한 화면에서 이어진다', function () {
    // 이 화면이 있는 이유 그대로를 한 번에 확인한다.
    $worker = Worker::factory()->create([
        'nationality' => 'VN',
        'status' => WorkerStatus::Active->value,
    ]);

    // 1) 농가 등록 — 기준정보와 같은 엔드포인트
    actingAs($this->admin)->postJson(route('admin.grid.farms.save'), [
        'added' => [['name' => '새로등록한농원', 'city_id' => $this->city->id]],
        'rows' => 'matching',
    ])->assertOk();

    $farm = Farm::where('name', '새로등록한농원')->firstOrFail();

    // 2) 수요 등록
    $demandId = actingAs($this->admin)->postJson(route('admin.matching.demand.store', $farm), [
        'nationality' => 'VN',
        'headcount' => 2,
        'gender' => 'any',
        'crop' => '딸기',
        'period_start' => '2027-03-01',
        'period_end' => '2027-06-30',
    ])->assertOk()->json('demand_id');

    // 3) 배정
    actingAs($this->admin)->postJson(route('admin.matching.store'), [
        'demand_id' => $demandId,
        'worker_ids' => [$worker->id],
    ])->assertOk();

    $placement = Placement::where('worker_id', $worker->id)->firstOrFail();

    expect($placement->farm_id)->toBe($farm->id)
        ->and($placement->status)->toBe(PlacementStatus::Proposed)
        // 기간은 수요에서 온다 — 이것 때문에 수요 없이는 배정을 만들 수 없다.
        ->and($placement->start_date->toDateString())->toBe('2027-03-01');
});

it('매칭 화면에서 저장하면 수요·배정 칸까지 돌려준다', function () {
    // 이걸 빠뜨리면 저장한 순간 [인력 배정] 칸이 빈칸이 되어,
    // 방금 등록한 농가에 사람을 붙일 수 없다.
    $rows = actingAs($this->admin)->postJson(route('admin.grid.farms.save'), [
        'added' => [['name' => '방금등록한농원']],
        'rows' => 'matching',
    ])->assertOk()->json('rows');

    expect($rows[0])->toHaveKeys(['demands', 'placed', 'assign']);
});

it('기준정보에서 저장하면 기준정보 칸만 돌려준다', function () {
    // 매칭 전용 칸을 늘 얹으면 기준정보 표에 뜻 모를 열이 생긴다.
    $rows = actingAs($this->admin)->postJson(route('admin.grid.farms.save'), [
        'added' => [['name' => '기준정보농원']],
    ])->assertOk()->json('rows');

    expect($rows[0])->not->toHaveKey('assign');
});

it('관리자가 아니면 농가 배정 화면을 열 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    actingAs($officer)->getJson(route('admin.matching.farm', $this->farm))->assertForbidden();
    actingAs($officer)->postJson(route('admin.matching.demand.store', $this->farm), [
        'nationality' => 'VN', 'headcount' => 1, 'gender' => 'any', 'crop' => '딸기',
        'period_start' => '2027-03-01', 'period_end' => '2027-06-30',
    ])->assertForbidden();

    expect(DemandRequest::count())->toBe(0);
});

it('농가 근로자 이름을 띄우면 열람 기록이 남는다', function () {
    // §7-6 — 누가 언제 어느 근로자를 봤는지.
    Placement::factory()->create(['farm_id' => $this->farm->id]);

    actingAs($this->admin)->getJson(route('admin.matching.farm', $this->farm))->assertOk();

    expect(Activity::where('log_name', 'personal-data-access')
        ->where('properties->reason', 'matching-farm')->exists())->toBeTrue();
});

it('배정 현황 표에서 체크한 건을 한 번에 확정한다', function () {
    // 표 안에는 버튼을 둘 수 없어 체크 → 툴바로 처리한다. 스무 건을 스무 번
    // 누르지 않아도 되는 편이 낫기도 하다.
    $a = Placement::factory()->create(['farm_id' => $this->farm->id, 'status' => PlacementStatus::Proposed]);
    $b = Placement::factory()->create(['farm_id' => $this->farm->id, 'status' => PlacementStatus::Proposed]);

    $res = actingAs($this->admin)->postJson(route('admin.matching.bulk'), [
        'action' => 'confirm',
        'ids' => [$a->id, $b->id],
    ])->assertOk();

    expect($a->fresh()->status)->toBe(PlacementStatus::Confirmed)
        ->and($b->fresh()->status)->toBe(PlacementStatus::Confirmed)
        ->and($res->json('message'))->toContain('2건')
        // 표를 다시 그릴 목록을 함께 준다.
        ->and($res->json('rows'))->toHaveCount(2)
        ->and($res->json('demand_rows'))->toBeArray();
});

it('한 건이 막혀도 나머지는 처리한다', function () {
    // 이미 확정된 건이 섞였다고 스무 건이 통째로 되돌아가면 무엇이 걸렸는지
    // 찾기만 어려워진다.
    $ok = Placement::factory()->create(['farm_id' => $this->farm->id, 'status' => PlacementStatus::Proposed]);
    $already = Placement::factory()->create(['farm_id' => $this->farm->id, 'status' => PlacementStatus::Cancelled]);

    $res = actingAs($this->admin)->postJson(route('admin.matching.bulk'), [
        'action' => 'confirm',
        'ids' => [$ok->id, $already->id],
    ])->assertOk();

    expect($ok->fresh()->status)->toBe(PlacementStatus::Confirmed)
        ->and($already->fresh()->status)->toBe(PlacementStatus::Cancelled)
        ->and($res->json('message'))->toContain('건너뜀')
        ->and($res->json('message'))->toContain('#'.$already->id);
});

it('일괄 취소도 사유를 남긴다', function () {
    $p = Placement::factory()->create(['farm_id' => $this->farm->id, 'status' => PlacementStatus::Confirmed]);

    actingAs($this->admin)->postJson(route('admin.matching.bulk'), [
        'action' => 'cancel',
        'ids' => [$p->id],
        'reason' => '농가 사정으로 수요 축소',
    ])->assertOk();

    expect($p->fresh()->status)->toBe(PlacementStatus::Cancelled)
        ->and($p->fresh()->note)->toBe('농가 사정으로 수요 축소');
});

it('아무것도 고르지 않으면 막는다', function () {
    actingAs($this->admin)->postJson(route('admin.matching.bulk'), [
        'action' => 'confirm', 'ids' => [],
    ])->assertStatus(422);
});

it('관리자가 아니면 일괄 처리를 못 한다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);
    $p = Placement::factory()->create(['farm_id' => $this->farm->id, 'status' => PlacementStatus::Proposed]);

    actingAs($officer)->postJson(route('admin.matching.bulk'), [
        'action' => 'confirm', 'ids' => [$p->id],
    ])->assertForbidden();

    expect($p->fresh()->status)->toBe(PlacementStatus::Proposed);
});

it('표에 그릴 수 있는 모양으로 내려온다', function () {
    // 표는 참/거짓이 아니라 읽을 글자가 필요하다 (엑셀로도 그대로 나간다).
    Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'placement_group_id' => (string) Str::uuid(),
    ]);
    DemandRequest::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => DemandStatus::Submitted,
    ]);

    actingAs($this->admin);

    expect(MatchingController::placementRows()[0]['group_label'])->toBe('그룹')
        ->and(MatchingController::rows()[0]['pick'])->toBe('인력 배정 ▸');
});

it('체크한 배정을 지우면 근로자가 풀리고 농가 자리가 빈다', function () {
    // 그냥 지우면 사람만 소리 없이 사라진다 — 취소를 거쳐야 자리가 실제로 빈다.
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active->value]);
    $p = Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'worker_id' => $worker->id,
        'status' => PlacementStatus::Confirmed,
    ]);
    ArrivalRecord::factory()->create(['placement_id' => $p->id]);

    $res = actingAs($this->admin)->postJson(route('admin.matching.bulk'), [
        'action' => 'delete',
        'ids' => [$p->id],
        'reason' => '중복 등록',
    ])->assertOk();

    expect(Placement::find($p->id))->toBeNull()
        // 지우기만 하고 없애지는 않는다 — 누가 어디에 있었는지가 증빙으로 남는다.
        ->and(Placement::withTrashed()->findOrFail($p->id)->status)->toBe(PlacementStatus::Cancelled)
        ->and(Placement::withTrashed()->findOrFail($p->id)->note)->toBe('중복 등록')
        ->and(ArrivalRecord::count())->toBe(0)
        ->and(Worker::unassigned()->pluck('id'))->toContain($worker->id)
        ->and($res->json('message'))->toContain('배정 1건을 삭제');

    $row = collect(MatchingController::farmRows())->firstWhere('id', $this->farm->id);
    expect($row['placed'])->toBe(0);
});

it('이미 취소된 배정도 목록에서 치울 수 있다', function () {
    // 삭제는 상태를 가리지 않는다 — 확정·취소 버튼과 다른 점이다.
    $p = Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => PlacementStatus::Cancelled,
    ]);

    actingAs($this->admin)->postJson(route('admin.matching.bulk'), [
        'action' => 'delete', 'ids' => [$p->id],
    ])->assertOk();

    expect(Placement::find($p->id))->toBeNull()
        ->and(MatchingController::placementRows())->toBe([]);
});

it('수요를 지워도 그 농가의 배정은 남는다', function () {
    // 배정은 농가에 매여 있지 수요에 매여 있지 않다. 잘못 적은 신청서를 지웠다고
    // 이미 그 농가에서 일하는 사람이 사라지면 안 된다.
    $demand = DemandRequest::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => DemandStatus::Submitted,
    ]);
    $placement = Placement::factory()->create([
        'farm_id' => $this->farm->id,
        'status' => PlacementStatus::Confirmed,
    ]);

    $res = actingAs($this->admin)->postJson(route('admin.matching.demands.delete'), [
        'ids' => [$demand->id],
    ])->assertOk();

    expect(DemandRequest::find($demand->id))->toBeNull()
        ->and(Placement::find($placement->id))->not->toBeNull()
        ->and(Placement::find($placement->id)->status)->toBe(PlacementStatus::Confirmed)
        ->and($res->json('message'))->toContain('배정은 농가에 매여 있어 그대로 남습니다')
        ->and($res->json('rows'))->toBe([])
        ->and($res->json('farm_rows'))->toBeArray();
});

it('수요를 지우면 그 수요를 보던 후보자의 연결을 끊는다', function () {
    // nullOnDelete 는 행이 실제로 지워질 때만 돈다 — 수요는 soft delete 라 돌지 않는다.
    $demand = DemandRequest::factory()->create(['farm_id' => $this->farm->id]);
    $candidate = Candidate::factory()
        ->create(['demand_request_id' => $demand->id]);

    actingAs($this->admin)->postJson(route('admin.matching.demands.delete'), [
        'ids' => [$demand->id],
    ])->assertOk();

    expect($candidate->fresh()->demand_request_id)->toBeNull()
        // 후보자 자체는 남는다 — 사람은 수요와 함께 사라지지 않는다.
        ->and($candidate->fresh())->not->toBeNull();
});

it('관리자가 아니면 수요를 지울 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);
    $demand = DemandRequest::factory()->create(['farm_id' => $this->farm->id]);

    actingAs($officer)->postJson(route('admin.matching.demands.delete'), [
        'ids' => [$demand->id],
    ])->assertForbidden();

    expect(DemandRequest::find($demand->id))->not->toBeNull();
});

it('지울 것을 고르지 않으면 막는다', function () {
    actingAs($this->admin)->postJson(route('admin.matching.demands.delete'), ['ids' => []])
        ->assertStatus(422);
    actingAs($this->admin)->postJson(route('admin.matching.bulk'), ['action' => 'delete', 'ids' => []])
        ->assertStatus(422);
});
