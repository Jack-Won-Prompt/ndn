<?php

declare(strict_types=1);

use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Models\Placement;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\LifeChecklistCheck;
use App\Domains\Monitoring\Models\LifeChecklistItem;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\SosStatus;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SosAlert;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\LifeChecklistSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * 대시보드 — 로그인 후 첫 화면의 집계.
 *
 * 숫자가 틀리면 밀린 일을 놓치므로(특히 SOS) 집계 자체보다 **역할 스코프**가
 * 중요하다. 시청·농가가 남의 범위 건수를 보면 안 된다.
 */
beforeEach(function () {
    foreach (UserRole::values() as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/** 농가 + 그 농가에 확정 배정된 근로자 */
function dashboardFarmWorker(?City $city = null, ?User $owner = null): array
{
    $farm = Farm::factory()->create([
        'city_id' => ($city ?? City::factory()->create())->id,
        'owner_user_id' => $owner?->id,
    ]);
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);
    $placement = Placement::factory()->confirmed()->create([
        'worker_id' => $worker->id,
        'farm_id' => $farm->id,
    ]);

    return [$farm, $worker, $placement];
}

function dashboardAdmin(UserRole $role = UserRole::NdnAdmin, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role->value);
    Sanctum::actingAs($user);

    return $user;
}

// ── 근로자 대시보드 ────────────────────────────────────────────────────────

it('근로자 대시보드는 인증이 필요하다', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

it('관리자 토큰으로는 근로자 대시보드를 볼 수 없다', function () {
    dashboardAdmin();

    $this->getJson('/api/v1/dashboard')->assertForbidden();
});

it('배정이 없는 근로자도 대시보드가 열린다', function () {
    Sanctum::actingAs(Worker::factory()->create(['status' => WorkerStatus::Active]));

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.placement', null)
        ->assertJsonPath('data.arrival', null)
        ->assertJsonPath('data.life_checklist.checked', 0);
});

it('확정 배정과 입국 진행 단계를 보여 준다', function () {
    [$farm, $worker, $placement] = dashboardFarmWorker();
    ArrivalRecord::create([
        'placement_id' => $placement->id,
        'status' => ArrivalStatus::Arrived,
        'scheduled_arrival_at' => now()->addDays(2),
    ]);

    Sanctum::actingAs($worker);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.placement.farm', $farm->name)
        ->assertJsonPath('data.arrival.status', ArrivalStatus::Arrived->value)
        ->assertJsonPath('data.arrival.step', ArrivalStatus::Arrived->step());
});

it('제안 상태 배정은 대시보드에 나오지 않는다 (확정만)', function () {
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);
    Placement::factory()->create([
        'worker_id' => $worker->id,
        'farm_id' => Farm::factory()->create()->id,
    ]);

    Sanctum::actingAs($worker);

    $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.placement', null);
});

it('생활 체크리스트 진행 정도를 알려 준다', function () {
    $this->seed(LifeChecklistSeeder::class);
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);

    foreach (LifeChecklistItem::query()->active()->take(3)->get() as $item) {
        LifeChecklistCheck::factory()->create([
            'worker_id' => $worker->id,
            'life_checklist_item_id' => $item->id,
        ]);
    }

    Sanctum::actingAs($worker);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.life_checklist.total', 12)
        ->assertJsonPath('data.life_checklist.checked', 3)
        ->assertJsonPath('data.life_checklist.completed', false);
});

it('꺼 둔 항목은 진행 계산에서 빠진다', function () {
    $this->seed(LifeChecklistSeeder::class);
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);

    foreach (LifeChecklistItem::query()->active()->get() as $item) {
        LifeChecklistCheck::factory()->create([
            'worker_id' => $worker->id,
            'life_checklist_item_id' => $item->id,
        ]);
    }
    LifeChecklistItem::where('code', 'living_costs')->update(['active' => false]);

    Sanctum::actingAs($worker);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.life_checklist.total', 11)
        ->assertJsonPath('data.life_checklist.checked', 11)
        ->assertJsonPath('data.life_checklist.completed', true);
});

it('진행 중인 민원만 건수에 잡힌다', function () {
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active]);
    SupportTicket::factory()->create(['worker_id' => $worker->id, 'status' => TicketStatus::Open]);
    SupportTicket::factory()->create(['worker_id' => $worker->id, 'status' => TicketStatus::Resolved]);

    Sanctum::actingAs($worker);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.counts.open_tickets', 1);
});

it('남의 건수가 내 대시보드에 섞이지 않는다', function () {
    $me = Worker::factory()->create(['status' => WorkerStatus::Active]);
    $other = Worker::factory()->create(['status' => WorkerStatus::Active]);
    SupportTicket::factory()->count(3)->create([
        'worker_id' => $other->id,
        'status' => TicketStatus::Open,
    ]);

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.counts.open_tickets', 0);
});

// ── 관리자 대시보드 ────────────────────────────────────────────────────────

it('관리자 대시보드는 포털 토큰이 있어야 한다', function () {
    $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();

    Sanctum::actingAs(Worker::factory()->create());
    $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
});

it('미확인 SOS 건수와 최장 대기 시간을 보여 준다', function () {
    [, $worker] = dashboardFarmWorker();
    SosAlert::create([
        'worker_id' => $worker->id,
        'alerted_at' => now()->subMinutes(90),
        'status' => SosStatus::Open,
    ]);
    SosAlert::create([
        'worker_id' => $worker->id,
        'alerted_at' => now()->subMinutes(10),
        'status' => SosStatus::Open,
    ]);
    SosAlert::create([
        'worker_id' => $worker->id,
        'alerted_at' => now()->subMinutes(200),
        'status' => SosStatus::Closed,
    ]);

    dashboardAdmin();

    $body = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

    expect($body['sos']['open'])->toBe(2)
        ->and($body['sos']['oldest_minutes'])->toBeGreaterThanOrEqual(89);
});

it('미확인 SOS 가 없으면 최장 대기는 null 이다', function () {
    dashboardAdmin();

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.sos.open', 0)
        ->assertJsonPath('data.sos.oldest_minutes', null);
});

it('승인 대기·검수 대기 건수를 집계한다', function () {
    Worker::factory()->count(2)->create(['status' => WorkerStatus::Pending]);
    Worker::factory()->create(['status' => WorkerStatus::Active]);
    OnboardingSubmission::factory()->create(['status' => OnboardingStatus::Submitted]);
    OnboardingSubmission::factory()->create(['status' => OnboardingStatus::Approved]);

    dashboardAdmin();

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.todo.worker_approval', 2)
        ->assertJsonPath('data.todo.onboarding_review', 1);
});

it('7일 안에 도착하는 입국 건만 임박으로 센다', function () {
    [, , $soon] = dashboardFarmWorker();
    [, , $later] = dashboardFarmWorker();

    ArrivalRecord::create([
        'placement_id' => $soon->id,
        'status' => ArrivalStatus::Scheduled,
        'scheduled_arrival_at' => now()->addDays(3),
    ]);
    ArrivalRecord::create([
        'placement_id' => $later->id,
        'status' => ArrivalStatus::Scheduled,
        'scheduled_arrival_at' => now()->addDays(30),
    ]);

    dashboardAdmin();

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.todo.arrivals_soon', 1);
});

it('인계 완료된 입국 건은 임박에서 빠진다', function () {
    [, , $placement] = dashboardFarmWorker();
    ArrivalRecord::create([
        'placement_id' => $placement->id,
        'status' => ArrivalStatus::HandedOver,
        'scheduled_arrival_at' => now()->addDay(),
    ]);

    dashboardAdmin();

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.todo.arrivals_soon', 0);
});

it('시청은 관할 지자체 건수만 본다', function () {
    $myCity = City::factory()->create();
    [, $mine] = dashboardFarmWorker($myCity);
    [, $other] = dashboardFarmWorker();

    SupportTicket::factory()->create(['worker_id' => $mine->id, 'status' => TicketStatus::Open]);
    SupportTicket::factory()->count(4)->create(['worker_id' => $other->id, 'status' => TicketStatus::Open]);
    SosAlert::create(['worker_id' => $other->id, 'alerted_at' => now(), 'status' => SosStatus::Open]);

    dashboardAdmin(UserRole::CityOfficer, ['city_id' => $myCity->id]);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.todo.open_tickets', 1)
        ->assertJsonPath('data.sos.open', 0)
        ->assertJsonPath('data.status.workers_active', 1);
});

it('농가는 자기 농가 건수만 본다', function () {
    $owner = User::factory()->create();
    [, $mine] = dashboardFarmWorker(null, $owner);
    dashboardFarmWorker();

    $owner->assignRole(UserRole::FarmOwner->value);
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.status.workers_active', 1)
        ->assertJsonPath('data.status.placements_confirmed', 1)
        ->assertJsonPath('meta.can_decide', false);
});

it('NDN 관리자만 판단 권한 플래그를 받는다', function () {
    dashboardAdmin();

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('meta.can_decide', true)
        ->assertJsonPath('meta.role', UserRole::NdnAdmin->value);
});

it('최근 한 달 고위험 점검만 현황에 잡힌다', function () {
    [$farm, $worker] = dashboardFarmWorker();

    $review = fn (RiskLevel $level, $at) => WorkReview::factory()->create([
        'worker_id' => $worker->id,
        'farm_id' => $farm->id,
        'risk_level' => $level,
        'reviewed_at' => $at,
    ]);

    $review(RiskLevel::High, now()->subDays(3));
    $review(RiskLevel::High, now()->subMonths(6));
    $review(RiskLevel::Low, now()->subDays(3));

    dashboardAdmin();

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.status.high_risk', 1);
});

it('대시보드 응답에 개인정보가 없다 (건수만)', function () {
    [, $worker] = dashboardFarmWorker();
    $worker->update(['passport_no' => 'M99887766']);

    dashboardAdmin();

    $body = $this->getJson('/api/v1/admin/dashboard')->assertOk()->getContent();

    expect($body)->not->toContain('M99887766')
        ->and($body)->not->toContain($worker->name);
});

// ── 목록 요약 (counts) ─────────────────────────────────────────────────────

it('목록 meta 의 상태별 건수는 필터와 무관하게 전체를 센다', function () {
    Worker::factory()->count(2)->create(['status' => WorkerStatus::Pending]);
    Worker::factory()->count(3)->create(['status' => WorkerStatus::Active]);

    dashboardAdmin();

    // 필터를 걸어도 요약 숫자는 그대로여야 한다 — 그래야 '대기 2건' 을 보고
    // 필터를 눌렀을 때 그 숫자가 사라지지 않는다.
    $filtered = $this->getJson('/api/v1/admin/workers?status=pending')->assertOk();

    expect($filtered->json('data'))->toHaveCount(2)
        ->and($filtered->json('meta.counts.pending'))->toBe(2)
        ->and($filtered->json('meta.counts.active'))->toBe(3);
});

it('요약 건수도 역할 스코프를 탄다', function () {
    $myCity = City::factory()->create();
    dashboardFarmWorker($myCity);
    dashboardFarmWorker();

    dashboardAdmin(UserRole::CityOfficer, ['city_id' => $myCity->id]);

    $this->getJson('/api/v1/admin/workers')
        ->assertOk()
        ->assertJsonPath('meta.counts.active', 1);
});

it('민원 목록 요약은 상태별 건수를 내려준다', function () {
    [, $worker] = dashboardFarmWorker();
    SupportTicket::factory()->count(2)->create([
        'worker_id' => $worker->id,
        'status' => TicketStatus::Open,
    ]);
    SupportTicket::factory()->create([
        'worker_id' => $worker->id,
        'status' => TicketStatus::Resolved,
    ]);

    dashboardAdmin();

    $this->getJson('/api/v1/admin/tickets?status=resolved')
        ->assertOk()
        ->assertJsonPath('meta.counts.open', 2)
        ->assertJsonPath('meta.counts.resolved', 1);
});
