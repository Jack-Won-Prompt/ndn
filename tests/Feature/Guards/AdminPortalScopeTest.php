<?php

declare(strict_types=1);

use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * 관리자 앱 역할별 격리 가드 (CLAUDE.md §7-4, §7-5).
 *
 * 시청은 관할 지자체, 농가는 자기 농가 근로자만 볼 수 있어야 한다.
 * 이 격리가 깨지면 다른 지역·다른 농가의 개인정보가 새므로 삭제 금지 가드다.
 */
beforeEach(function () {
    foreach (UserRole::values() as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/** 지자체 + 농가 + 그 농가에 배정된 근로자 한 세트 */
function makeFarmWithWorker(?City $city = null, ?User $owner = null): array
{
    $city ??= City::factory()->create();
    $farm = Farm::factory()->create([
        'city_id' => $city->id,
        'owner_user_id' => $owner?->id,
    ]);
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);
    Placement::factory()->confirmed()->create([
        'worker_id' => $worker->id,
        'farm_id' => $farm->id,
    ]);

    return [$city, $farm, $worker];
}

function actingAsRole(UserRole $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role->value);
    Sanctum::actingAs($user);

    return $user;
}

it('근로자 토큰으로는 관리자 API 에 접근할 수 없다', function () {
    Sanctum::actingAs(Worker::factory()->create());

    $this->getJson('/api/v1/admin/workers')->assertForbidden();
});

it('인증 없이는 관리자 API 에 접근할 수 없다', function () {
    $this->getJson('/api/v1/admin/workers')->assertUnauthorized();
});

it('포털 허용 역할이 아니면 차단된다 (송출기관·대리점)', function (UserRole $role) {
    actingAsRole($role);

    $this->getJson('/api/v1/admin/workers')->assertForbidden();
})->with([UserRole::SendingAgency, UserRole::PartnerAgency]);

it('NDN 관리자는 모든 근로자를 본다', function () {
    [, , $a] = makeFarmWithWorker();
    [, , $b] = makeFarmWithWorker();
    Worker::factory()->create(); // 미배정 근로자

    actingAsRole(UserRole::NdnAdmin);

    $ids = collect($this->getJson('/api/v1/admin/workers')->assertOk()->json('data'))
        ->pluck('id');

    expect($ids)->toContain($a->id)->toContain($b->id)
        ->and($ids)->toHaveCount(3);
});

it('시청 담당자는 관할 지자체 근로자만 본다', function () {
    $myCity = City::factory()->create();
    [, , $mine] = makeFarmWithWorker($myCity);
    [, , $other] = makeFarmWithWorker(); // 다른 지자체

    actingAsRole(UserRole::CityOfficer, ['city_id' => $myCity->id]);

    $ids = collect($this->getJson('/api/v1/admin/workers')->assertOk()->json('data'))
        ->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

it('농가는 자기 농가 근로자만 본다', function () {
    $owner = User::factory()->create();
    [, , $mine] = makeFarmWithWorker(null, $owner);
    [, , $other] = makeFarmWithWorker();

    $owner->assignRole(UserRole::FarmOwner->value);
    Sanctum::actingAs($owner);

    $ids = collect($this->getJson('/api/v1/admin/workers')->assertOk()->json('data'))
        ->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

it('스코프 밖 근로자의 상세는 404 다 (존재를 알려주지 않는다)', function () {
    [, , $other] = makeFarmWithWorker();

    actingAsRole(UserRole::CityOfficer, ['city_id' => City::factory()->create()->id]);

    $this->getJson("/api/v1/admin/workers/{$other->id}")->assertNotFound();
});

it('시청·농가는 상태를 변경할 수 없다 (조회 전용)', function (UserRole $role) {
    $city = City::factory()->create();
    [, , $worker] = makeFarmWithWorker($city);
    $worker->update(['status' => WorkerStatus::Pending]);

    actingAsRole($role, ['city_id' => $city->id]);

    $this->postJson("/api/v1/admin/workers/{$worker->id}/approve")->assertForbidden();

    expect($worker->refresh()->status)->toBe(WorkerStatus::Pending);
})->with([UserRole::CityOfficer, UserRole::FarmOwner]);

it('NDN 관리자는 가입을 승인할 수 있다', function () {
    $worker = Worker::factory()->create(['status' => WorkerStatus::Pending]);

    actingAsRole(UserRole::NdnAdmin);

    $this->postJson("/api/v1/admin/workers/{$worker->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', WorkerStatus::Active->value);

    expect($worker->refresh()->status)->toBe(WorkerStatus::Active);
});

it('근로자 목록 응답에 여권번호 원문이 없다', function () {
    Worker::factory()->create(['passport_no' => 'M12345678']);

    actingAsRole(UserRole::NdnAdmin);

    $body = $this->getJson('/api/v1/admin/workers')->assertOk()->getContent();

    expect($body)->not->toContain('M12345678');
});

it('상세의 여권번호는 마스킹돼 내려온다', function () {
    $worker = Worker::factory()->create(['passport_no' => 'M12345678']);

    actingAsRole(UserRole::NdnAdmin);

    $this->getJson("/api/v1/admin/workers/{$worker->id}")
        ->assertOk()
        ->assertJsonPath('data.passport_masked', 'M••••••••');
});

it('근로자 개인정보 열람이 감사 로그에 남는다 (§7-6)', function () {
    $worker = Worker::factory()->create();
    $admin = actingAsRole(UserRole::NdnAdmin);

    $this->getJson("/api/v1/admin/workers/{$worker->id}")->assertOk();

    $log = Activity::where('log_name', 'personal-data')
        ->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($admin->id)
        ->and($log->properties['worker_ids'])->toContain($worker->id);
});
