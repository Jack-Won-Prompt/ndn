<?php

declare(strict_types=1);

use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Admin\ConsoleController;
use App\Models\User;
use App\Shared\Enums\Gender;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * 콘솔 매칭 화면 — 본사가 웹에서 직접 농가에 인력을 붙인다.
 *
 * 배정을 만드는 기능은 관리자 앱(API)에만 있었다. 판단은 그때와 같은 Action 이
 * 하므로, 여기서는 콘솔 경로로도 같은 규칙이 지켜지는지를 본다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);

    $this->demand = DemandRequest::factory()->submitted()->create([
        'nationality' => 'VN',
        'headcount' => 2,
        'age_min' => null,
        'age_max' => null,
        'gender' => Gender::Any,
        'allow_siblings' => true,
    ]);
});

function ndnWorker(array $attrs = []): Worker
{
    return Worker::factory()->create(array_merge([
        'nationality' => 'VN',
        'status' => WorkerStatus::Active->value,
    ], $attrs));
}

it('수요를 열면 추천 인력과 배정 현황이 함께 나온다', function () {
    $match = ndnWorker(['name' => '응우옌']);
    // 국적이 달라 추천에는 안 걸리지만, 현장 사정으로 배정해야 할 수 있다.
    $other = ndnWorker(['name' => '수닐', 'nationality' => 'LK']);

    $res = actingAs($this->admin)
        ->getJson(route('admin.matching.show', $this->demand))
        ->assertOk();

    expect($res->json('demand.headcount'))->toBe(2)
        ->and($res->json('demand.remaining'))->toBe(2)
        ->and(collect($res->json('candidates'))->pluck('id'))->toContain($match->id)
        ->and(collect($res->json('others'))->pluck('id'))->toContain($other->id);
});

it('추천 인력을 보면 열람 기록이 남는다', function () {
    // 이름이 화면에 뜨는 순간부터 개인정보 열람이다(§7-6).
    ndnWorker();

    actingAs($this->admin)->getJson(route('admin.matching.show', $this->demand))->assertOk();

    $log = Activity::where('log_name', 'personal-data-access')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->properties['reason'])->toBe('matching-candidates');
});

it('여러 명을 한 번에 배정하고 형제·가족은 한 그룹으로 묶는다', function () {
    $a = ndnWorker();
    $b = ndnWorker();

    actingAs($this->admin)
        ->postJson(route('admin.matching.store'), [
            'demand_id' => $this->demand->id,
            'worker_ids' => [$a->id, $b->id],
            'as_group' => true,
        ])
        ->assertOk()
        ->assertJsonPath('count', 2);

    $groups = Placement::pluck('placement_group_id')->unique();

    expect(Placement::count())->toBe(2)
        ->and($groups)->toHaveCount(1)
        ->and($groups->first())->not->toBeNull();
});

it('요청 인원을 넘겨 배정할 수 없다', function () {
    // 정원 초과는 담당자가 고칠 수 있는 문제라 사유가 그대로 화면에 나와야 한다.
    $workers = collect(range(1, 3))->map(fn () => ndnWorker());

    actingAs($this->admin)
        ->postJson(route('admin.matching.store'), [
            'demand_id' => $this->demand->id,
            'worker_ids' => $workers->pluck('id')->all(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', '요청 인원(2명)을 초과합니다. 남은 자리: 2명');

    expect(Placement::count())->toBe(0);
});

it('확정하면 입국 준비 기록이 함께 만들어진다', function () {
    $worker = ndnWorker();

    actingAs($this->admin)->postJson(route('admin.matching.store'), [
        'demand_id' => $this->demand->id,
        'worker_ids' => [$worker->id],
    ])->assertOk();

    $placement = Placement::firstOrFail();

    actingAs($this->admin)
        ->postJson(route('admin.matching.confirm', $placement))
        ->assertOk();

    expect($placement->refresh()->status)->toBe(PlacementStatus::Confirmed)
        ->and(ArrivalRecord::where('placement_id', $placement->id)->exists())->toBeTrue();
});

it('취소하면 사유가 남고 그 인력은 다시 후보로 돌아온다', function () {
    $worker = ndnWorker();

    actingAs($this->admin)->postJson(route('admin.matching.store'), [
        'demand_id' => $this->demand->id,
        'worker_ids' => [$worker->id],
    ])->assertOk();

    $placement = Placement::firstOrFail();

    actingAs($this->admin)
        ->postJson(route('admin.matching.cancel', $placement), ['reason' => '농가 사정으로 수요 축소'])
        ->assertOk();

    expect($placement->refresh()->status)->toBe(PlacementStatus::Cancelled)
        ->and($placement->note)->toBe('농가 사정으로 수요 축소');

    $res = actingAs($this->admin)->getJson(route('admin.matching.show', $this->demand))->assertOk();

    expect(collect($res->json('candidates'))->pluck('id'))->toContain($worker->id);
});

it('이미 배정된 인력은 다시 배정되지 않는다', function () {
    $worker = ndnWorker();

    Placement::factory()->create([
        'worker_id' => $worker->id,
        'status' => PlacementStatus::Proposed->value,
    ]);

    actingAs($this->admin)
        ->postJson(route('admin.matching.store'), [
            'demand_id' => $this->demand->id,
            'worker_ids' => [$worker->id],
        ])
        ->assertStatus(422);

    expect(Placement::count())->toBe(1);
});

it('관리자가 아니면 배정할 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    $worker = ndnWorker();

    actingAs($officer)
        ->postJson(route('admin.matching.store'), [
            'demand_id' => $this->demand->id,
            'worker_ids' => [$worker->id],
        ])
        ->assertForbidden();

    expect(Placement::count())->toBe(0);
});

it('콘솔 사이드바와 화면 디스패치에 매칭이 걸려 있다', function () {
    // 라우트만 있고 메뉴가 없으면 아무도 못 찾는다.
    actingAs($this->admin)->get(url('admin/screen/matching'))
        ->assertOk()
        ->assertSee('농가 매칭·배정');

    $keys = collect(ConsoleController::menu())
        ->flatMap(fn (array $g) => $g['items'])->pluck('key');

    expect($keys)->toContain('matching');
});
