<?php

declare(strict_types=1);

use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Enums\TicketType;
use App\Domains\Support\Enums\WorkerExitReason;
use App\Domains\Support\Enums\WorkerExitStatus;
use App\Domains\Support\Enums\WorkerExitType;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\WorkerExit;
use App\Http\Controllers\Admin\ConsoleController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * 조기 귀국 결정 · 이탈·연락두절 상태 (업무흐름 §8).
 *
 * 이 기능의 값어치는 "결정 한 번이 세 곳에 같이 반영된다" 는 데 있다.
 * 사건 기록 · 근로자 계정 · 농가 배정. 지금까지는 status 만 손으로 바꿔서
 * 귀국한 사람이 농가 정원을 계속 차지하고 있었다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);

    $this->worker = Worker::factory()->create([
        'name' => '응우옌',
        'status' => WorkerStatus::Active->value,
    ]);

    $this->placement = Placement::factory()->create([
        'worker_id' => $this->worker->id,
        'status' => PlacementStatus::Confirmed->value,
    ]);
});

function openExit(array $override = []): TestResponse
{
    return actingAs(test()->admin)->postJson(route('admin.worker-exits.store'), array_merge([
        'worker_id' => test()->worker->id,
        'type' => WorkerExitType::EarlyReturn->value,
        'reason' => WorkerExitReason::Illness->value,
        'reason_detail' => '허리 부상으로 작업이 어렵습니다.',
        'occurred_on' => now()->subDays(2)->toDateString(),
    ], $override));
}

function advance(WorkerExit $exit, WorkerExitStatus $to, array $data = []): TestResponse
{
    return actingAs(test()->admin)->postJson(
        route('admin.worker-exits.advance', $exit),
        array_merge(['status' => $to->value], $data),
    );
}

it('조기 귀국 신청을 열면 현재 배정이 함께 기록된다', function () {
    openExit()->assertOk();

    $exit = WorkerExit::firstOrFail();

    expect($exit->type)->toBe(WorkerExitType::EarlyReturn)
        ->and($exit->status)->toBe(WorkerExitStatus::Requested)
        ->and($exit->reason)->toBe(WorkerExitReason::Illness)
        // 어느 배정에서 빠지는지가 남아야 나중에 농가별 집계가 된다.
        ->and($exit->placement_id)->toBe($this->placement->id)
        ->and($exit->created_by)->toBe($this->admin->id);

    // 신청 단계에서는 아직 아무것도 바뀌지 않는다.
    expect($this->worker->refresh()->status)->toBe(WorkerStatus::Active)
        ->and($this->placement->refresh()->status)->toBe(PlacementStatus::Confirmed);
});

it('출국까지 끝나면 계정이 귀국으로 바뀌고 농가 자리가 비워진다', function () {
    openExit()->assertOk();
    $exit = WorkerExit::firstOrFail();

    advance($exit, WorkerExitStatus::Approved)->assertOk();

    // 승인만으로는 아직 자리를 비우지 않는다 — 출국 전이다.
    expect($this->worker->refresh()->status)->toBe(WorkerStatus::Active)
        ->and($this->placement->refresh()->status)->toBe(PlacementStatus::Confirmed);

    advance($exit->refresh(), WorkerExitStatus::Completed, [
        'departed_on' => now()->toDateString(),
    ])->assertOk();

    expect($exit->refresh()->status)->toBe(WorkerExitStatus::Completed)
        ->and($exit->departed_on->toDateString())->toBe(now()->toDateString())
        ->and($this->worker->refresh()->status)->toBe(WorkerStatus::Returned)
        // 이게 핵심이다. 남겨 두면 농가 정원이 계속 잠긴다.
        ->and($this->placement->refresh()->status)->toBe(PlacementStatus::Cancelled);
});

it('반려하면 계속 근무한다', function () {
    openExit()->assertOk();
    $exit = WorkerExit::firstOrFail();

    advance($exit, WorkerExitStatus::Rejected, ['note' => '농가와 협의해 계속 근무'])->assertOk();

    expect($exit->refresh()->status)->toBe(WorkerExitStatus::Rejected)
        ->and($this->worker->refresh()->status)->toBe(WorkerStatus::Active)
        ->and($this->placement->refresh()->status)->toBe(PlacementStatus::Confirmed);
});

it('연락두절로 등록하면 앱 로그인이 곧바로 막힌다', function () {
    // 소재가 불명한 계정이 그대로 살아 있으면 안 된다.
    openExit([
        'type' => WorkerExitType::Absconded->value,
        'reason' => null,
        'occurred_on' => now()->subDays(5)->toDateString(),
        'noticed_on' => now()->subDays(3)->toDateString(),
    ])->assertOk();

    $exit = WorkerExit::firstOrFail();

    expect($exit->status)->toBe(WorkerExitStatus::Unreachable)
        // 인지 시점에 사유를 모르는 게 정상이다.
        ->and($exit->reason)->toBe(WorkerExitReason::Unknown)
        ->and($exit->noticed_on->toDateString())->toBe(now()->subDays(3)->toDateString());

    $worker = $this->worker->refresh();
    expect($worker->status)->toBe(WorkerStatus::Inactive)
        ->and($worker->status->canLogin())->toBeFalse();

    // 아직 이탈 확정은 아니다 — 배정은 살아 있다.
    expect($this->placement->refresh()->status)->toBe(PlacementStatus::Confirmed);
});

it('이탈로 확정하면 계정이 이탈이 되고 신고 내용이 남는다', function () {
    openExit(['type' => WorkerExitType::Absconded->value, 'reason' => null])->assertOk();
    $exit = WorkerExit::firstOrFail();

    advance($exit, WorkerExitStatus::Confirmed, [
        'reason' => WorkerExitReason::Misconduct->value,
        'reported' => true,
        'reported_on' => now()->toDateString(),
        'report_ref' => '2026-충남-1234',
    ])->assertOk();

    $exit->refresh();

    expect($exit->status)->toBe(WorkerExitStatus::Confirmed)
        // 사유는 인지 때 '미상' 이었다가 확정 시점에 정해진다.
        ->and($exit->reason)->toBe(WorkerExitReason::Misconduct)
        ->and($exit->reported)->toBeTrue()
        ->and($exit->report_ref)->toBe('2026-충남-1234')
        ->and($this->worker->refresh()->status)->toBe(WorkerStatus::Absconded)
        ->and($this->placement->refresh()->status)->toBe(PlacementStatus::Cancelled);
});

it('이탈 확정 뒤에 나타나도 되돌릴 수 있다', function () {
    // 확정한 뒤 본인이 나타나는 일이 실제로 있다. 길을 막아 두면 새 건을 또 만든다.
    openExit(['type' => WorkerExitType::Absconded->value, 'reason' => null])->assertOk();
    $exit = WorkerExit::firstOrFail();

    advance($exit, WorkerExitStatus::Confirmed)->assertOk();
    advance($exit->refresh(), WorkerExitStatus::Recovered, ['note' => '본인 연락 옴'])->assertOk();

    expect($exit->refresh()->status)->toBe(WorkerExitStatus::Recovered)
        ->and($this->worker->refresh()->status)->toBe(WorkerStatus::Active);
});

it('유형에 없는 상태로는 넘어갈 수 없다', function () {
    // 조기 귀국에 '이탈 확정' 이 있을 수 없고, 이탈에 '반려' 가 있을 수 없다.
    openExit()->assertOk();
    $early = WorkerExit::firstOrFail();

    advance($early, WorkerExitStatus::Confirmed)->assertStatus(422);
    expect($early->refresh()->status)->toBe(WorkerExitStatus::Requested);

    $other = Worker::factory()->create(['status' => WorkerStatus::Active->value]);
    actingAs($this->admin)->postJson(route('admin.worker-exits.store'), [
        'worker_id' => $other->id,
        'type' => WorkerExitType::Absconded->value,
        'occurred_on' => now()->toDateString(),
    ])->assertOk();

    $absconded = WorkerExit::where('worker_id', $other->id)->firstOrFail();
    advance($absconded, WorkerExitStatus::Rejected)->assertStatus(422);
    expect($absconded->refresh()->status)->toBe(WorkerExitStatus::Unreachable);
});

it('같은 유형의 건을 두 번 열 수 없다', function () {
    // 두 담당자가 각각 열면 통계가 두 배가 된다.
    openExit()->assertOk();

    openExit()->assertStatus(422)
        ->assertJsonPath('message', '응우옌 님은 이미 진행 중인 조기 귀국 건이 있습니다 (#1).');

    expect(WorkerExit::count())->toBe(1);
});

it('종결된 건이 있으면 새로 열 수 있다', function () {
    openExit()->assertOk();
    advance(WorkerExit::firstOrFail(), WorkerExitStatus::Rejected)->assertOk();

    // 반려된 뒤 다시 신청하는 것은 정상이다.
    openExit()->assertOk();

    expect(WorkerExit::count())->toBe(2);
});

it('앱에서 올라온 조기 귀국 민원이 자동으로 연결되고 함께 닫힌다', function () {
    // 앱에서 신청했는데 콘솔 건과 따로 놀면 근로자는 답을 못 받는다.
    $ticket = SupportTicket::factory()->create([
        'worker_id' => $this->worker->id,
        'type' => TicketType::EarlyReturn->value,
        'status' => TicketStatus::Open->value,
    ]);

    openExit()->assertOk();
    $exit = WorkerExit::firstOrFail();

    expect($exit->support_ticket_id)->toBe($ticket->id);

    advance($exit, WorkerExitStatus::Approved)->assertOk();
    // 승인은 아직 출국 전이라 민원을 열어 둔다.
    expect($ticket->refresh()->status)->toBe(TicketStatus::Open);

    advance($exit->refresh(), WorkerExitStatus::Completed)->assertOk();
    expect($ticket->refresh()->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->resolved_at)->not->toBeNull();
});

it('승인 대기 근로자에게는 건을 만들 수 없다', function () {
    $pending = Worker::factory()->create(['status' => WorkerStatus::Pending->value]);

    actingAs($this->admin)->postJson(route('admin.worker-exits.store'), [
        'worker_id' => $pending->id,
        'type' => WorkerExitType::Absconded->value,
        'occurred_on' => now()->toDateString(),
    ])->assertStatus(422);

    expect(WorkerExit::count())->toBe(0);
});

it('연락두절 경과 일수는 종결되면 멈춘다', function () {
    // 지난 건이 매일 늘어나면 목록이 거짓말을 한다.
    $exit = WorkerExit::factory()->absconded()->create([
        'worker_id' => $this->worker->id,
        'occurred_on' => now()->subDays(10)->toDateString(),
    ]);

    expect($exit->daysUnreachable())->toBe(10);

    $exit->forceFill([
        'status' => WorkerExitStatus::Recovered,
        'decided_at' => now()->subDays(4),
    ])->save();

    expect($exit->refresh()->daysUnreachable())->toBe(6);
});

it('조기 귀국 건에는 경과 일수가 없다', function () {
    $exit = WorkerExit::factory()->create(['worker_id' => $this->worker->id]);

    expect($exit->daysUnreachable())->toBeNull();
});

it('결정이 감사 기록에 남는다', function () {
    openExit()->assertOk();
    $exit = WorkerExit::firstOrFail();

    advance($exit, WorkerExitStatus::Approved)->assertOk();

    $log = Activity::where('log_name', 'worker-exit')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['from'])->toBe('requested')
        ->and($log->properties['to'])->toBe('approved')
        ->and($log->properties['worker_id'])->toBe($this->worker->id)
        ->and($log->causer_id)->toBe($this->admin->id);
});

it('상세를 열면 개인정보 열람 기록이 남는다', function () {
    openExit()->assertOk();
    $exit = WorkerExit::firstOrFail();

    actingAs($this->admin)->getJson(route('admin.worker-exits.show', $exit))->assertOk();

    $log = Activity::where('log_name', 'personal-data-access')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->properties['reason'])->toBe('worker-exit');
});

it('상세는 지금 누를 수 있는 버튼만 알려 준다', function () {
    openExit(['type' => WorkerExitType::Absconded->value, 'reason' => null])->assertOk();
    $exit = WorkerExit::firstOrFail();

    $res = actingAs($this->admin)->getJson(route('admin.worker-exits.show', $exit))->assertOk();

    expect(collect($res->json('next'))->pluck('value')->all())
        ->toBe(['confirmed', 'recovered'])
        ->and($res->json('needs_report'))->toBeTrue();
});

it('관리자가 아니면 처리할 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    actingAs($officer)->postJson(route('admin.worker-exits.store'), [
        'worker_id' => $this->worker->id,
        'type' => WorkerExitType::Absconded->value,
        'occurred_on' => now()->toDateString(),
    ])->assertForbidden();

    expect(WorkerExit::count())->toBe(0);
});

it('콘솔 사이드바와 화면 디스패치에 걸려 있고 배지가 진행 중 건수를 센다', function () {
    openExit()->assertOk();

    actingAs($this->admin)->get(url('admin/screen/exits'))
        ->assertOk()
        ->assertSee('조기귀국 · 이탈 관리');

    $keys = collect(ConsoleController::menu())->flatMap(fn (array $g) => $g['items'])->pluck('key');

    expect($keys)->toContain('exits')
        ->and(ConsoleController::badgeCounts()['exits'])->toBe(1);

    // 종결되면 배지에서 빠진다.
    advance(WorkerExit::firstOrFail(), WorkerExitStatus::Rejected)->assertOk();
    expect(ConsoleController::badgeCounts()['exits'])->toBe(0);
});

it('근로자 상세에 조기귀국·이탈 이력이 함께 나온다', function () {
    openExit()->assertOk();

    $res = actingAs($this->admin)
        ->getJson(url('admin/screen/workers/'.$this->worker->id.'?format=json'))
        ->assertOk();

    expect($res->json('exits.0.type'))->toBe('조기 귀국')
        ->and($res->json('exits.0.reason'))->toBe('질환·부상')
        ->and($res->json('exits.0.label'))->toBe('신청일');
});
