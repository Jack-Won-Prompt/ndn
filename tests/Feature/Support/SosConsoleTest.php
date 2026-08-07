<?php

declare(strict_types=1);

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\SosStatus;
use App\Domains\Support\Models\SosAlert;
use App\Http\Controllers\Admin\SosController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

/**
 * 긴급 SOS 상황판 (콘솔).
 *
 * 관리자 앱에는 있었지만 웹 콘솔에는 화면이 없었다. 사무실에서 긴급 건을 볼 수
 * 없다는 뜻이라 가장 먼저 채운 구멍이다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
});

function sosAlert(SosStatus $status, $alertedAt, ?float $lat = null, ?float $lng = null): SosAlert
{
    return SosAlert::create([
        'worker_id' => Worker::factory()->create()->id,
        'alerted_at' => $alertedAt,
        'status' => $status,
        'lat' => $lat,
        'lng' => $lng,
    ]);
}

it('미확인이 위로, 그 안에서 오래 방치된 것부터 온다', function () {
    $closed = sosAlert(SosStatus::Closed, now()->subDays(2));
    $recentOpen = sosAlert(SosStatus::Open, now()->subMinutes(5));
    $oldOpen = sosAlert(SosStatus::Open, now()->subHours(6));
    $ack = sosAlert(SosStatus::Acknowledged, now()->subHours(9));

    $ids = collect(SosController::rows())->pluck('id')->all();

    expect($ids)->toBe([$oldOpen->id, $recentOpen->id, $ack->id, $closed->id]);
});

it('경과 시간을 읽기 쉽게 보여 준다', function () {
    sosAlert(SosStatus::Open, now()->subMinutes(135));

    expect(SosController::rows()[0]['elapsed'])->toBe('2시간 15분');
});

it('좌표가 있으면 지도 주소를 함께 준다', function () {
    sosAlert(SosStatus::Open, now(), 37.5665, 126.9780);

    $row = SosController::rows()[0];

    expect($row['coords'])->toContain('37.5665')
        ->and($row['map_url'])->toContain('37.5665');
});

it('좌표가 없어도 목록에 나온다 (실내·권한 거부)', function () {
    sosAlert(SosStatus::Open, now());

    $row = SosController::rows()[0];

    expect($row['coords'])->toBe('—')
        ->and($row['map_url'])->toBeNull();
});

it('화면이 열리고 미확인 건수를 띄운다', function () {
    sosAlert(SosStatus::Open, now()->subHour());
    sosAlert(SosStatus::Closed, now()->subDay());

    actingAs($this->admin)
        ->get('/admin/screen/sos')
        ->assertOk()
        ->assertSee('긴급 SOS')
        ->assertSee('미확인 1건');

    expect(SosController::openCount())->toBe(1);
});

it('확인 처리하면 누가 언제 했는지 남는다', function () {
    $alert = sosAlert(SosStatus::Open, now()->subHour());

    actingAs($this->admin)
        ->postJson(route('admin.sos.status', $alert), ['status' => 'acknowledged'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('open_count', 0);

    $alert->refresh();

    expect($alert->status)->toBe(SosStatus::Acknowledged)
        ->and($alert->acknowledged_by)->toBe($this->admin->id)
        ->and($alert->acknowledged_at)->not->toBeNull();
});

it('허용되지 않는 상태 전이는 거부한다', function () {
    $closed = sosAlert(SosStatus::Closed, now()->subDay());

    actingAs($this->admin)
        ->postJson(route('admin.sos.status', $closed), ['status' => 'acknowledged'])
        ->assertStatus(422)
        ->assertJsonPath('ok', false);
});

it('목록을 여는 것도 개인정보 열람으로 기록된다', function () {
    // §7-6: 이 화면은 근로자 이름이 그대로 보인다.
    $alert = sosAlert(SosStatus::Open, now());

    actingAs($this->admin)->get('/admin/screen/sos')->assertOk();

    $log = Activity::where('log_name', 'personal-data')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['context'])->toBe('console-sos')
        ->and($log->properties['worker_ids'])->toContain($alert->worker_id);
});

it('로그인하지 않으면 상황판을 볼 수 없다', function () {
    // 콘솔은 웹 세션 인증이라 로그인 화면으로 돌려보낸다.
    get('/admin/screen/sos')->assertRedirect();

    $alert = sosAlert(SosStatus::Open, now());
    postJson(route('admin.sos.status', $alert), ['status' => 'acknowledged'])->assertRedirect();

    expect($alert->refresh()->status)->toBe(SosStatus::Open);
});
