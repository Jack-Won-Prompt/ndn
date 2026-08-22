<?php

declare(strict_types=1);

use App\Domains\Recruitment\Enums\ScreeningStatus;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Admin\SignupApprovalController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

/**
 * 가입 승인 표 — 체크한 신청을 한 번에 심사한다.
 *
 * 쉰 명이 한꺼번에 들어오는 명단에서 쉰 번 누르지 않아도 되어야 한다.
 * 다만 '합격' 은 계정을 열고 알림을 보내는 되돌릴 수 없는 동작이라,
 * 무엇이 함께 일어나는지 화면이 먼저 말한다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
    Mail::fake();

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
});

function pendingWorker(array $attrs = []): Worker
{
    return Worker::factory()->create(array_merge([
        'status' => WorkerStatus::Pending->value,
        'screening_status' => null,
    ], $attrs));
}

it('표에 읽을 수 있는 글자로 내려온다', function () {
    pendingWorker(['name' => '심사대기자']);

    $row = collect(SignupApprovalController::rows())->firstWhere('name', '심사대기자');

    expect($row['files_label'])->toBe('없음')
        ->and($row['detail'])->toBe('상세 ▸')
        ->and($row['screening_label'])->not->toBeEmpty();
});

it('체크한 신청을 한 번에 합격 처리한다', function () {
    $a = pendingWorker();
    $b = pendingWorker();

    $res = actingAs($this->admin)->postJson(route('admin.signups.screen-bulk'), [
        'decision' => ScreeningStatus::Passed->value,
        'ids' => [$a->id, $b->id],
    ])->assertOk();

    // 합격 = 계정이 열린다.
    expect($a->fresh()->status)->toBe(WorkerStatus::Active)
        ->and($b->fresh()->status)->toBe(WorkerStatus::Active)
        ->and($res->json('message'))->toContain('2건')
        // 표를 다시 그릴 목록과 남은 건수를 함께 준다.
        ->and($res->json('rows'))->toBeArray()
        ->and($res->json('open_count'))->toBe(0);
});

it('보류는 승인 대기로 남는다', function () {
    $w = pendingWorker();

    actingAs($this->admin)->postJson(route('admin.signups.screen-bulk'), [
        'decision' => ScreeningStatus::Held->value,
        'ids' => [$w->id],
    ])->assertOk();

    expect($w->fresh()->status)->toBe(WorkerStatus::Pending)
        ->and($w->fresh()->screening_status)->toBe(ScreeningStatus::Held);
});

it('한 건이 막혀도 나머지는 처리한다', function () {
    // 이미 결정 난 건이 섞였다고 통째로 되돌아가면 무엇이 걸렸는지 찾기만 어려워진다.
    $ok = pendingWorker();
    $done = Worker::factory()->create([
        'status' => WorkerStatus::Active->value,
        'screening_status' => ScreeningStatus::Passed->value,
    ]);

    $res = actingAs($this->admin)->postJson(route('admin.signups.screen-bulk'), [
        'decision' => ScreeningStatus::Passed->value,
        'ids' => [$ok->id, $done->id],
    ])->assertOk();

    expect($ok->fresh()->status)->toBe(WorkerStatus::Active)
        ->and($res->json('message'))->toBeString();
});

it('건너뛴 사유에 이름을 적지 않는다', function () {
    // 메시지가 명단 사본이 되면 안 된다 (§7-3).
    $w = Worker::factory()->create([
        'name' => '이름노출금지',
        'status' => WorkerStatus::Rejected->value,
        'screening_status' => ScreeningStatus::Failed->value,
    ]);

    $res = actingAs($this->admin)->postJson(route('admin.signups.screen-bulk'), [
        'decision' => ScreeningStatus::Passed->value,
        'ids' => [$w->id],
    ])->assertOk();

    expect($res->json('message'))->not->toContain('이름노출금지');
});

it('여러 명에게 같은 보완 항목을 한 번에 보낸다', function () {
    // 같은 서류가 빠진 사람이 무더기로 나오는 것이 보통이다.
    $a = pendingWorker(['email' => 'a@example.com']);
    $b = pendingWorker(['email' => 'b@example.com']);

    $res = actingAs($this->admin)->postJson(route('admin.signups.supplement-bulk'), [
        'ids' => [$a->id, $b->id],
        'items' => ['passport'],
    ])->assertOk();

    expect($a->fresh()->screening_status)->toBe(ScreeningStatus::SupplementRequested)
        ->and($b->fresh()->screening_status)->toBe(ScreeningStatus::SupplementRequested)
        ->and($res->json('message'))->toContain('2명');
});

it('고른 것이 없으면 막는다', function () {
    actingAs($this->admin)->postJson(route('admin.signups.screen-bulk'), [
        'decision' => ScreeningStatus::Passed->value, 'ids' => [],
    ])->assertStatus(422);

    actingAs($this->admin)->postJson(route('admin.signups.supplement-bulk'), [
        'ids' => [1], 'items' => [],
    ])->assertStatus(422);
});

it('관리자가 아니면 심사할 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);
    $w = pendingWorker();

    actingAs($officer)->postJson(route('admin.signups.screen-bulk'), [
        'decision' => ScreeningStatus::Passed->value, 'ids' => [$w->id],
    ])->assertForbidden();

    expect($w->fresh()->status)->toBe(WorkerStatus::Pending);
});

it('화면이 표로 그려진다', function () {
    pendingWorker(['name' => '표에보일사람']);

    $html = actingAs($this->admin)->get(url('admin/screen/signups'))->assertOk()->getContent();

    expect($html)->toContain('grid-signups')
        ->and($html)->toContain('wwConsole(')
        ->and($html)->toContain('표에보일사람');
});
