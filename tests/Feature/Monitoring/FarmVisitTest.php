<?php

declare(strict_types=1);

use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Actions\RecordFarmVisitAction;
use App\Domains\Monitoring\Enums\FarmVisitStatus;
use App\Domains\Monitoring\Models\FarmVisit;
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
