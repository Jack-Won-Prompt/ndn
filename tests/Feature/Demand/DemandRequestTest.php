<?php

declare(strict_types=1);

use App\Domains\Demand\Actions\CreateDemandRequestAction;
use App\Domains\Demand\Actions\SubmitDemandRequestAction;
use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Events\DemandRequestSubmitted;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Models\User;
use App\Shared\Enums\Gender;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('Action 이 draft 상태로 수요 신청을 생성한다', function () {
    $farm = Farm::factory()->create();

    $demand = app(CreateDemandRequestAction::class)->execute($farm, [
        'nationality' => 'BD',
        'headcount' => 5,
        'gender' => Gender::Any->value,
        'crop' => '딸기',
        'period_start' => '2026-09-01',
        'period_end' => '2027-02-01',
    ]);

    expect($demand->status)->toBe(DemandStatus::Draft)
        ->and($demand->farm_id)->toBe($farm->id)
        ->and($demand->headcount)->toBe(5);
});

it('제출 Action 이 상태를 submitted 로 바꾸고 이벤트를 발생시킨다', function () {
    Event::fake();

    $demand = DemandRequest::factory()->create(['status' => DemandStatus::Draft]);

    app(SubmitDemandRequestAction::class)->execute($demand);

    expect($demand->fresh()->status)->toBe(DemandStatus::Submitted)
        ->and($demand->fresh()->submitted_at)->not->toBeNull();

    Event::assertDispatched(DemandRequestSubmitted::class);
});

it('이미 제출된 신청은 다시 제출할 수 없다', function () {
    $demand = DemandRequest::factory()->submitted()->create();

    app(SubmitDemandRequestAction::class)->execute($demand);
})->throws(RuntimeException::class);

it('농가 소유자는 자기 농가에 수요 신청을 생성할 수 있다', function () {
    $owner = User::factory()->create();
    $owner->assignRole(UserRole::FarmOwner->value);
    $farm = Farm::factory()->create(['owner_user_id' => $owner->id]);

    actingAs($owner);

    post(route('demand.store', $farm), [
        'nationality' => 'VN',
        'headcount' => 3,
        'gender' => Gender::Female->value,
        'crop' => '토마토',
        'period_start' => '2026-09-01',
        'period_end' => '2027-02-01',
    ])->assertRedirect();

    expect(DemandRequest::where('farm_id', $farm->id)->count())->toBe(1);
});

it('송출기관 역할은 수요 신청을 생성할 수 없다', function () {
    $agency = User::factory()->create();
    $agency->assignRole(UserRole::SendingAgency->value);
    $farm = Farm::factory()->create();

    actingAs($agency);

    post(route('demand.store', $farm), [
        'nationality' => 'VN',
        'headcount' => 3,
        'gender' => Gender::Any->value,
        'crop' => '오이',
        'period_start' => '2026-09-01',
        'period_end' => '2027-02-01',
    ])->assertForbidden();

    expect(DemandRequest::count())->toBe(0);
});

it('농가는 목록에서 본인 농가의 수요만 본다', function () {
    $owner = User::factory()->create();
    $owner->assignRole(UserRole::FarmOwner->value);
    $mine = Farm::factory()->create(['owner_user_id' => $owner->id, 'name' => '내농장']);
    $other = Farm::factory()->create(['name' => '남의농장']);

    DemandRequest::factory()->create(['farm_id' => $mine->id]);
    DemandRequest::factory()->create(['farm_id' => $other->id]);

    actingAs($owner);

    get(route('demand.index'))->assertOk()
        ->assertSee('내농장')
        ->assertDontSee('남의농장');
});

it('농가는 자기 소유가 아닌 농가로 수요를 신청할 수 없다', function () {
    $owner = User::factory()->create();
    $owner->assignRole(UserRole::FarmOwner->value);
    Farm::factory()->create(['owner_user_id' => $owner->id]); // 본인 농가
    $other = Farm::factory()->create();                        // 남의 농가

    actingAs($owner);

    post(route('demand.store', $other), [
        'nationality' => 'VN',
        'headcount' => 3,
        'gender' => Gender::Any->value,
        'crop' => '오이',
        'period_start' => '2026-09-01',
        'period_end' => '2027-02-01',
    ])->assertForbidden();

    expect(DemandRequest::where('farm_id', $other->id)->count())->toBe(0);
});

it('농가는 수요 신청 폼을 볼 수 있다', function () {
    $owner = User::factory()->create();
    $owner->assignRole(UserRole::FarmOwner->value);
    Farm::factory()->create(['owner_user_id' => $owner->id]);

    actingAs($owner);

    get(route('demand.create'))->assertOk()->assertSee('새 수요 신청');
});

it('NDN 관리자(본사)는 임의 농가로 수요를 신청할 수 있다', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);
    $farm = Farm::factory()->create();

    actingAs($admin);

    post(route('demand.store', $farm), [
        'nationality' => 'VN',
        'headcount' => 4,
        'gender' => Gender::Any->value,
        'crop' => '상추',
        'period_start' => '2026-09-01',
        'period_end' => '2027-02-01',
    ])->assertRedirect();

    expect(DemandRequest::where('farm_id', $farm->id)->count())->toBe(1);
});
