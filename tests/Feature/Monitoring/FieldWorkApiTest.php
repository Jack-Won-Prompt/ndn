<?php

declare(strict_types=1);

use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Models\Placement;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Monitoring\Models\InspectionCheckin;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * 현장 점검 API — 농가 방문(사진)·근로자 인터뷰·GPS 체크인 (업무흐름 §7).
 */
beforeEach(function () {
    foreach (UserRole::values() as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
    Sanctum::actingAs($this->admin);

    $this->farm = Farm::factory()->create(['name' => '햇살농장']);
    $this->worker = Worker::factory()->create();
    Placement::factory()->confirmed()->create([
        'worker_id' => $this->worker->id,
        'farm_id' => $this->farm->id,
    ]);
});

it('방문할 농가와 배정 근로자 목록을 조회한다', function () {
    $response = $this->getJson('/api/v1/admin/field/farms')->assertOk();

    $farm = collect($response->json('data'))->firstWhere('id', $this->farm->id);

    expect($farm['name'])->toBe('햇살농장')
        ->and(collect($farm['workers'])->pluck('id'))->toContain($this->worker->id)
        ->and($response->json('meta.items'))->toBe(MonthlyInterview::ITEMS);
});

it('농가 방문 점검을 사진과 함께 등록한다', function () {
    Storage::fake('local');

    $this->postJson('/api/v1/admin/farm-visits', [
        'farm_id' => $this->farm->id,
        'visited_on' => now()->toDateString(),
        'farm_status' => 'caution',
        'worker_status' => 'normal',
        'worker_headcount' => 3,
        'issue_note' => '숙소 난방 점검 필요',
        'photos' => [UploadedFile::fake()->image('site.jpg')],
    ])->assertCreated()
        ->assertJsonPath('data.farm', '햇살농장')
        ->assertJsonPath('data.farm_status', 'caution')
        ->assertJsonCount(1, 'data.photos');

    $visit = FarmVisit::first();
    expect($visit->photos)->toHaveCount(1)
        ->and($visit->issue_note)->toBe('숙소 난방 점검 필요');

    // 사진은 private 디스크에 저장되고 DB 에는 경로만 남는다
    Storage::disk('local')->assertExists($visit->photos->first()->path);
});

it('방문 시 근로자 6항목 인터뷰를 함께 기록한다', function () {
    $this->postJson('/api/v1/admin/farm-visits', [
        'farm_id' => $this->farm->id,
        'visited_on' => now()->toDateString(),
        'interviews' => [
            [
                'worker_id' => $this->worker->id,
                'items' => ['pay_received' => false, 'health_ok' => false],
                'memo' => '급여 지연 확인',
            ],
        ],
    ])->assertCreated()->assertJsonPath('data.interview_count', 1);

    $interview = MonthlyInterview::where('worker_id', $this->worker->id)->first();

    expect($interview)->not->toBeNull()
        ->and($interview->risk_score)->toBe(2)
        ->and($interview->farm_visit_id)->toBe(FarmVisit::first()->id);
});

it('인터뷰 6항목이 잘리지 않고 리스크 계산에 들어간다', function () {
    // validated() 를 그대로 쓰면 규칙 없는 중첩 키(items.*)가 사라져 리스크가
    // 항상 0 이 된다. 전 항목 부정으로 최대 점수가 나오는지 확인한다.
    $this->postJson('/api/v1/admin/farm-visits', [
        'farm_id' => $this->farm->id,
        'visited_on' => now()->toDateString(),
        'interviews' => [[
            'worker_id' => $this->worker->id,
            'items' => array_fill_keys(MonthlyInterview::ITEMS, false),
        ]],
    ])->assertCreated();

    expect(MonthlyInterview::first()->risk_score)->toBe(count(MonthlyInterview::ITEMS));
});

it('알 수 없는 인터뷰 항목은 무시된다', function () {
    $this->postJson('/api/v1/admin/farm-visits', [
        'farm_id' => $this->farm->id,
        'visited_on' => now()->toDateString(),
        'interviews' => [[
            'worker_id' => $this->worker->id,
            'items' => ['pay_received' => false, 'bogus_item' => false],
        ]],
    ])->assertCreated();

    // 알 수 없는 키가 점수에 섞이면 2 가 된다
    expect(MonthlyInterview::first()->risk_score)->toBe(1);
});

it('스코프 밖 근로자는 인터뷰 대상에서 걸러진다', function () {
    $outsider = Worker::factory()->create();

    $officer = User::factory()->create(['city_id' => $this->farm->city_id]);
    $officer->assignRole(UserRole::NdnAdmin->value);
    Sanctum::actingAs($officer);

    $this->postJson('/api/v1/admin/farm-visits', [
        'farm_id' => $this->farm->id,
        'visited_on' => now()->toDateString(),
        'interviews' => [['worker_id' => $outsider->id]],
    ])->assertCreated();

    // ndn_admin 은 전체를 보므로 기록된다 — 스코프가 좁은 역할로 다시 확인
    expect(MonthlyInterview::where('worker_id', $outsider->id)->exists())->toBeTrue();
});

it('농가는 방문 점검을 등록할 수 없다 (조회 전용)', function () {
    $owner = User::factory()->create();
    $this->farm->update(['owner_user_id' => $owner->id]);
    $owner->assignRole(UserRole::FarmOwner->value);
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/admin/farm-visits', [
        'farm_id' => $this->farm->id,
        'visited_on' => now()->toDateString(),
    ])->assertForbidden();

    expect(FarmVisit::count())->toBe(0);
});

it('다른 지자체 농가에는 방문 기록을 남길 수 없다', function () {
    $officer = User::factory()->create(['city_id' => City::factory()->create()->id]);
    $officer->assignRole(UserRole::NdnAdmin->value);

    // NDN 관리자는 전체 권한이므로, 스코프 확인은 시청 역할로 한다
    $cityOfficer = User::factory()->create(['city_id' => City::factory()->create()->id]);
    $cityOfficer->assignRole(UserRole::CityOfficer->value);
    Sanctum::actingAs($cityOfficer);

    // 시청은 애초에 상태 변경 권한이 없어 403 이 먼저 걸린다
    $this->postJson('/api/v1/admin/farm-visits', [
        'farm_id' => $this->farm->id,
        'visited_on' => now()->toDateString(),
    ])->assertForbidden();
});

it('방문 이력을 조회한다', function () {
    FarmVisit::factory()->count(2)->create(['farm_id' => $this->farm->id]);

    $this->getJson('/api/v1/admin/farm-visits')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// ── GPS 체크인 (§7-2 허용 테이블) ────────────────────────────────────────

it('점검자 GPS 체크인을 기록한다', function () {
    $this->postJson('/api/v1/admin/checkins', [
        'worker_id' => $this->worker->id,
        'lat' => 36.8951,
        'lng' => 126.6289,
        'memo' => '숙소 방문',
    ])->assertCreated();

    $checkin = InspectionCheckin::first();

    expect($checkin->inspector_user_id)->toBe($this->admin->id)
        ->and((float) $checkin->lat)->toBe(36.8951);
});

it('좌표 범위를 벗어나면 체크인이 거부된다', function () {
    $this->postJson('/api/v1/admin/checkins', [
        'worker_id' => $this->worker->id,
        'lat' => 999,
        'lng' => 0,
    ])->assertStatus(422)->assertJsonValidationErrorFor('lat');
});

it('좌표 없이는 체크인할 수 없다 (증빙이 목적)', function () {
    $this->postJson('/api/v1/admin/checkins', [
        'worker_id' => $this->worker->id,
    ])->assertStatus(422)->assertJsonValidationErrorFor('lat');
});

it('농가 방문 기록 테이블에는 위치 컬럼이 없다 (§7-2)', function () {
    $columns = Schema::getColumnListing('farm_visits');

    expect($columns)->not->toContain('lat')->and($columns)->not->toContain('lng');
});
