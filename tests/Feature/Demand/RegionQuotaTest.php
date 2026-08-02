<?php

declare(strict_types=1);

use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Admin\RegionController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;

/**
 * 지역(시군)별 모집 정원 + 배치 현황 집계 (업무흐름 §1·§4).
 */
beforeEach(fn () => $this->seed(RoleSeeder::class));

function regionAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::NdnAdmin->value);

    return $user;
}

it('모집을 중지한 지역은 가입 지역 목록에서 빠진다', function () {
    City::factory()->create(['name' => '당진시', 'region' => '충청남도', 'recruiting' => true]);
    City::factory()->create(['name' => '여주시', 'region' => '경기도', 'recruiting' => false]);

    $res = $this->getJson('/api/v1/cities')->assertOk();

    expect($res->json('meta.count'))->toBe(1);
    expect($res->json('data.0.label'))->toBe('충청남도 당진시');
});

it('정원이 찬 지역은 목록에서 빠지고 잔여 인원이 함께 내려온다', function () {
    $full = City::factory()->create(['name' => '당진시', 'quota' => 2]);
    $open = City::factory()->create(['name' => '여주시', 'region' => '경기도', 'quota' => 5]);

    Worker::factory()->count(2)->create(['city_id' => $full->id]);
    Worker::factory()->count(1)->create(['city_id' => $open->id]);

    $res = $this->getJson('/api/v1/cities')->assertOk();

    expect($res->json('meta.count'))->toBe(1);
    expect($res->json('data.0.id'))->toBe($open->id);
    expect($res->json('data.0.remaining'))->toBe(4);
});

it('정원이 찬 지역으로는 가입할 수 없다', function () {
    $city = City::factory()->create(['quota' => 1]);
    Worker::factory()->create(['city_id' => $city->id]);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Tran Van Test',
        'email' => 'full@ndn.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'nationality' => 'vn',
        'city_id' => $city->id,
        'locale' => 'vi',
        'passport_no' => 'C7777777',
    ])->assertStatus(422)->assertJsonValidationErrorFor('city_id');

    expect(Worker::where('email', 'full@ndn.test')->exists())->toBeFalse();
});

it('지역별 현황에 지원자·배치 인원·농가 수가 지역 단위로 집계된다', function () {
    $dangjin = City::factory()->create(['name' => '당진시', 'region' => '충청남도', 'quota' => 10]);
    $yeoju = City::factory()->create(['name' => '여주시', 'region' => '경기도']);

    // 당진 농가 2곳, 여주 1곳
    $farmA = Farm::factory()->create(['city_id' => $dangjin->id]);
    $farmB = Farm::factory()->create(['city_id' => $dangjin->id]);
    $farmC = Farm::factory()->create(['city_id' => $yeoju->id]);

    // 당진 지원자 3명(1명 승인대기), 여주 1명
    Worker::factory()->count(2)->create(['city_id' => $dangjin->id, 'status' => WorkerStatus::Active]);
    Worker::factory()->create(['city_id' => $dangjin->id, 'status' => WorkerStatus::Pending]);
    Worker::factory()->create(['city_id' => $yeoju->id, 'status' => WorkerStatus::Active]);

    // 확정 배정: 당진 농가에 2건, 여주 농가에 1건, 그리고 취소 1건(집계 제외)
    Placement::factory()->create(['farm_id' => $farmA->id, 'worker_id' => Worker::factory(), 'status' => PlacementStatus::Confirmed]);
    Placement::factory()->create(['farm_id' => $farmB->id, 'worker_id' => Worker::factory(), 'status' => PlacementStatus::Confirmed]);
    Placement::factory()->create(['farm_id' => $farmC->id, 'worker_id' => Worker::factory(), 'status' => PlacementStatus::Confirmed]);
    Placement::factory()->create(['farm_id' => $farmA->id, 'worker_id' => Worker::factory(), 'status' => PlacementStatus::Cancelled]);

    $rows = collect(RegionController::rows())->keyBy('name');

    expect($rows['당진시']['applicants'])->toBe(3);
    expect($rows['당진시']['pending'])->toBe(1);
    expect($rows['당진시']['placed'])->toBe(2);
    expect($rows['당진시']['farms'])->toBe(2);
    expect($rows['당진시']['remaining'])->toBe(7);

    expect($rows['여주시']['placed'])->toBe(1);
    expect($rows['여주시']['farms'])->toBe(1);
    // 정원 미설정 → 제한 없음
    expect($rows['여주시']['quota'])->toBeNull();
    expect($rows['여주시']['remaining'])->toBeNull();
});

it('지역 드릴다운에 농가별 배치 인원이 나온다', function () {
    $city = City::factory()->create(['name' => '당진시']);
    $farm = Farm::factory()->create(['city_id' => $city->id, 'name' => '햇살농장']);
    Placement::factory()->count(2)->create(['farm_id' => $farm->id, 'status' => PlacementStatus::Confirmed]);

    $res = $this->actingAs(regionAdmin())
        ->getJson(route('admin.regions.show', $city))
        ->assertOk();

    expect($res->json('farms.0.name'))->toBe('햇살농장');
    expect($res->json('farms.0.placed'))->toBe(2);
});

it('콘솔 지역별 화면이 열린다', function () {
    City::factory()->create(['name' => '당진시', 'region' => '충청남도']);

    $this->actingAs(regionAdmin())
        ->get(url('admin/screen/regions'))
        ->assertOk()
        ->assertSee('지역별 모집·배치')
        ->assertSee('당진시');
});
