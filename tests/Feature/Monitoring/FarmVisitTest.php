<?php

declare(strict_types=1);

use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Monitoring\Actions\RecordFarmVisitAction;
use App\Domains\Monitoring\Enums\FarmVisitStatus;
use App\Domains\Monitoring\Enums\InterviewSource;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
});

function ndnAdmin(): User
{
    $u = User::factory()->create();
    $u->assignRole(UserRole::NdnAdmin->value);

    return $u;
}

it('Action 이 방문 점검과 현장 사진을 함께 저장한다', function () {
    $farm = Farm::factory()->create();
    $admin = ndnAdmin();

    $visit = app(RecordFarmVisitAction::class)->execute($farm, $admin, [
        'visited_on' => '2026-07-27',
        'farm_status' => FarmVisitStatus::Caution->value,
        'worker_status' => FarmVisitStatus::Normal->value,
        'worker_headcount' => 5,
        'issue_note' => '기숙사 난방 고장',
    ], [
        UploadedFile::fake()->image('p1.jpg'),
        UploadedFile::fake()->image('p2.jpg'),
    ]);

    expect($visit->farm_status)->toBe(FarmVisitStatus::Caution)
        ->and($visit->worker_status)->toBe(FarmVisitStatus::Normal)
        ->and($visit->worker_headcount)->toBe(5)
        ->and($visit->photos()->count())->toBe(2);

    foreach ($visit->photos as $photo) {
        Storage::disk('local')->assertExists($photo->path);
        expect($photo->isImage())->toBeTrue();
    }
});

it('본사(ndn_admin) 콘솔에서 사진과 함께 방문 점검을 등록한다', function () {
    $farm = Farm::factory()->create();

    actingAs(ndnAdmin());

    post(route('admin.farm-visits.store'), [
        'farm_id' => $farm->id,
        'visited_on' => '2026-07-27',
        'farm_status' => 'normal',
        'worker_status' => 'caution',
        'worker_headcount' => 7,
        'issue_note' => '테스트 애로사항',
        'photos' => [
            UploadedFile::fake()->image('site1.jpg'),
            UploadedFile::fake()->image('site2.jpg'),
        ],
    ])->assertOk()->assertJsonPath('ok', true);

    $visit = FarmVisit::where('farm_id', $farm->id)->first();
    expect($visit)->not->toBeNull();
    expect($visit->photos()->count())->toBe(2);
});

it('필수값(농가·상태)이 없으면 등록에 실패한다', function () {
    actingAs(ndnAdmin());

    post(route('admin.farm-visits.store'), [
        'visited_on' => '2026-07-27',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('이미지가 아닌 파일은 사진으로 업로드할 수 없다', function () {
    $farm = Farm::factory()->create();
    actingAs(ndnAdmin());

    post(route('admin.farm-visits.store'), [
        'farm_id' => $farm->id,
        'visited_on' => '2026-07-27',
        'farm_status' => 'normal',
        'worker_status' => 'normal',
        'photos' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('현장 사진은 참여 관리자만 스트리밍할 수 있고 불일치 시 404', function () {
    $farm = Farm::factory()->create();
    $admin = ndnAdmin();
    $visit = app(RecordFarmVisitAction::class)->execute($farm, $admin, [
        'visited_on' => '2026-07-27',
        'farm_status' => 'normal',
        'worker_status' => 'normal',
    ], [UploadedFile::fake()->image('p.jpg')]);
    $photo = $visit->photos()->first();

    actingAs($admin);

    get(route('admin.farm-visits.photo', ['farmVisit' => $visit->id, 'photo' => $photo->id]))
        ->assertOk();

    // 다른 방문 id 로 접근하면 404
    $other = app(RecordFarmVisitAction::class)->execute($farm, $admin, [
        'visited_on' => '2026-07-27', 'farm_status' => 'normal', 'worker_status' => 'normal',
    ], []);
    get(route('admin.farm-visits.photo', ['farmVisit' => $other->id, 'photo' => $photo->id]))
        ->assertNotFound();
});

function confirmPlacement(Farm $farm, Worker $worker): void
{
    Placement::factory()->create([
        'farm_id' => $farm->id,
        'worker_id' => $worker->id,
        'status' => PlacementStatus::Confirmed->value,
    ]);
}

it('방문 시 근로자 개개인 인터뷰가 방문에 연결되어 기록되고 리스크가 산정된다', function () {
    $farm = Farm::factory()->create();
    $admin = ndnAdmin();
    $w1 = Worker::factory()->create();
    $w2 = Worker::factory()->create();
    confirmPlacement($farm, $w1);
    confirmPlacement($farm, $w2);

    $visit = app(RecordFarmVisitAction::class)->execute($farm, $admin, [
        'visited_on' => '2026-07-27',
        'farm_status' => 'normal',
        'worker_status' => 'caution',
    ], [], [
        ['worker_id' => $w1->id, 'items' => array_fill_keys(MonthlyInterview::ITEMS, true), 'memo' => '양호'],
        ['worker_id' => $w2->id, 'items' => ['pay_received' => false, 'no_flight_signs' => false] + array_fill_keys(MonthlyInterview::ITEMS, true), 'memo' => '급여 지연'],
    ]);

    expect($visit->interviews()->count())->toBe(2);

    $a = $visit->interviews()->where('worker_id', $w1->id)->first();
    $b = $visit->interviews()->where('worker_id', $w2->id)->first();

    expect($a->source)->toBe(InterviewSource::Inspector)
        ->and($a->farm_visit_id)->toBe($visit->id)
        ->and($a->risk_level)->toBe(RiskLevel::Low)
        ->and($b->risk_level)->toBe(RiskLevel::Medium)   // 부정 2개
        ->and($b->pay_received)->toBeFalse();
});

it('콘솔 등록에서 그 농가 배정 근로자만 인터뷰가 기록된다(타 농가 근로자는 무시)', function () {
    $farm = Farm::factory()->create();
    $mine = Worker::factory()->create();
    $stranger = Worker::factory()->create();  // 이 농가에 배정 안 됨
    confirmPlacement($farm, $mine);

    actingAs(ndnAdmin());

    post(route('admin.farm-visits.store'), [
        'farm_id' => $farm->id,
        'visited_on' => '2026-07-27',
        'farm_status' => 'normal',
        'worker_status' => 'normal',
        'interviews' => [
            $mine->id => array_fill_keys(MonthlyInterview::ITEMS, '1') + ['memo' => 'ok'],
            $stranger->id => array_fill_keys(MonthlyInterview::ITEMS, '0') + ['memo' => '침입'],
        ],
    ])->assertOk();

    $visit = FarmVisit::where('farm_id', $farm->id)->first();
    expect($visit->interviews()->count())->toBe(1);
    expect($visit->interviews()->first()->worker_id)->toBe($mine->id);
    expect(MonthlyInterview::where('worker_id', $stranger->id)->exists())->toBeFalse();
});

it('농가 근로자 조회 API 는 배정 확정 근로자만 반환한다', function () {
    $farm = Farm::factory()->create();
    $confirmed = Worker::factory()->create();
    $proposed = Worker::factory()->create();
    confirmPlacement($farm, $confirmed);
    Placement::factory()->create(['farm_id' => $farm->id, 'worker_id' => $proposed->id, 'status' => PlacementStatus::Proposed->value]);

    actingAs(ndnAdmin());

    $res = get(route('admin.farm-visits.workers', $farm))->assertOk();
    $ids = collect($res->json('workers'))->pluck('id');
    expect($ids)->toContain($confirmed->id)->not->toContain($proposed->id);
});

it('근로자 인터뷰 이력 API 는 해당 근로자의 인터뷰를 최신순으로 반환한다', function () {
    $farm = Farm::factory()->create();
    $admin = ndnAdmin();
    $worker = Worker::factory()->create();
    confirmPlacement($farm, $worker);

    app(RecordFarmVisitAction::class)->execute($farm, $admin, [
        'visited_on' => '2026-06-27', 'farm_status' => 'normal', 'worker_status' => 'normal',
    ], [], [['worker_id' => $worker->id, 'items' => array_fill_keys(MonthlyInterview::ITEMS, true)]]);
    app(RecordFarmVisitAction::class)->execute($farm, $admin, [
        'visited_on' => '2026-07-27', 'farm_status' => 'normal', 'worker_status' => 'normal',
    ], [], [['worker_id' => $worker->id, 'items' => array_fill_keys(MonthlyInterview::ITEMS, true)]]);

    actingAs($admin);
    $res = get(route('admin.farm-visits.worker-history', $worker))->assertOk();
    expect($res->json('history'))->toHaveCount(2);
    expect($res->json('history.0.date'))->toBe('2026-07-27'); // 최신순
});

it('일반 사용자는 방문 점검을 등록할 수 없다', function () {
    $farm = Farm::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(UserRole::FarmOwner->value);

    actingAs($user);

    post(route('admin.farm-visits.store'), [
        'farm_id' => $farm->id,
        'visited_on' => '2026-07-27',
        'farm_status' => 'normal',
        'worker_status' => 'normal',
    ])->assertForbidden();
});
