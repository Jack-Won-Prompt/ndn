<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AccessLogController;
use App\Models\AccessLog;
use App\Models\User;
use App\Shared\Enums\UserRole;
use App\Shared\Support\IpCountry;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;

/**
 * 접속 로그 — 접속 국가 표시와 시각 기준.
 *
 * 국가는 이미 저장하는 IP 에서 나라 단위까지만 판별한다. 좌표는 다루지 않으며
 * 근로자 위치는 여전히 sos_alerts·inspection_checkins 두 곳에만 있다(§7-2).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);

    $this->csv = storage_path('app/'.IpCountry::DATA_PATH);
    @mkdir(dirname($this->csv), 0775, true);
});

afterEach(function () {
    @unlink($this->csv);
});

/** 판별표를 깐다 — 실제 파일과 같은 형식(숫자 범위, ISO-2). */
function writeGeoCsv(string $path, array $rows): void
{
    $lines = array_map(
        fn (array $r) => implode(',', [ip2long($r[0]), ip2long($r[1]), $r[2]]),
        $rows,
    );
    file_put_contents($path, implode("\n", $lines)."\n");
}

it('사설·루프백 주소는 내부로 가려낸다', function () {
    expect(IpCountry::of('127.0.0.1'))->toBe(IpCountry::LOCAL)
        ->and(IpCountry::of('192.168.0.10'))->toBe(IpCountry::LOCAL)
        ->and(IpCountry::of('10.1.2.3'))->toBe(IpCountry::LOCAL)
        ->and(IpCountry::of('::1'))->toBe(IpCountry::LOCAL);

    expect(IpCountry::label(IpCountry::LOCAL))->toBe('내부');
});

it('판별표가 없으면 나라를 지어내지 않는다', function () {
    expect(IpCountry::hasData())->toBeFalse();
    expect(IpCountry::of('1.2.3.4'))->toBeNull();
    expect(IpCountry::label(null))->toBe('미상');
});

it('판별표가 있으면 대역으로 나라를 찾는다', function () {
    writeGeoCsv($this->csv, [
        ['1.0.0.0', '1.0.255.255', 'VN'],
        ['211.0.0.0', '211.255.255.255', 'KR'],
        ['103.5.0.0', '103.5.255.255', 'BD'],
    ]);

    expect(IpCountry::hasData())->toBeTrue();
    expect(IpCountry::of('211.104.5.9'))->toBe('KR');
    expect(IpCountry::of('1.0.128.1'))->toBe('VN');
    expect(IpCountry::of('103.5.77.2'))->toBe('BD');
    // 표에 없는 대역은 미상. 가까운 대역으로 끌어다 붙이지 않는다.
    expect(IpCountry::of('8.8.8.8'))->toBeNull();
});

it('나라 이름을 한국어로 보여 준다', function () {
    expect(IpCountry::label('KR'))->toBe('대한민국')
        ->and(IpCountry::label('VN'))->toBe('베트남');
    // 목록에 없는 코드는 코드 그대로 — 빈칸으로 두지 않는다.
    expect(IpCountry::label('ZW'))->toBe('ZW');
});

it('접속하면 국가가 함께 기록된다', function () {
    writeGeoCsv($this->csv, [['211.0.0.0', '211.255.255.255', 'KR']]);

    actingAs($this->admin)
        ->withServerVariables(['REMOTE_ADDR' => '211.104.5.9'])
        ->get('/admin')
        ->assertOk();

    $log = AccessLog::latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->ip)->toBe('211.104.5.9')
        ->and($log->country)->toBe('KR');
});

it('해외 접속을 목록·집계에서 가려낸다', function () {
    AccessLog::create(['actor' => '게스트', 'method' => 'GET', 'path' => '/', 'status' => 200,
        'ip' => '211.1.1.1', 'country' => 'KR', 'created_at' => now()]);
    AccessLog::create(['actor' => '게스트', 'method' => 'GET', 'path' => '/', 'status' => 200,
        'ip' => '1.0.1.1', 'country' => 'VN', 'created_at' => now()]);
    AccessLog::create(['actor' => '게스트', 'method' => 'GET', 'path' => '/', 'status' => 200,
        'ip' => '127.0.0.1', 'country' => IpCountry::LOCAL, 'created_at' => now()]);

    // 국내·내부는 해외가 아니다.
    expect(AccessLogController::summary()['foreign'])->toBe(1);

    $rows = collect(AccessLogController::rows());
    expect($rows->firstWhere('country', 'VN')['is_foreign'])->toBeTrue();
    expect($rows->firstWhere('country', 'KR')['is_foreign'])->toBeFalse();
    expect($rows->firstWhere('country', IpCountry::LOCAL)['is_foreign'])->toBeFalse();

    $byCountry = collect(AccessLogController::byCountry())->keyBy('code');
    expect($byCountry['VN']['count'])->toBe(1)
        ->and($byCountry['VN']['label'])->toBe('베트남')
        ->and($byCountry['VN']['foreign'])->toBeTrue();
});

it('시각과 함께 상대 시간을 보여 준다', function () {
    AccessLog::create(['actor' => '게스트', 'method' => 'GET', 'path' => '/', 'status' => 200,
        'ip' => '127.0.0.1', 'created_at' => now()->subMinutes(3)]);

    expect(AccessLogController::rows()[0]['ago'])->toBe('3분 전');
});

it('저장 시각이 미래면 숨기지 않고 이상으로 표시한다', function () {
    // 저장·표시 타임존이 어긋나면 이렇게 드러난다. 조용히 넘어가면 못 찾는다.
    AccessLog::create(['actor' => '게스트', 'method' => 'GET', 'path' => '/', 'status' => 200,
        'ip' => '127.0.0.1', 'created_at' => now()->addHours(9)]);

    expect(AccessLogController::rows()[0]['ago'])->toBe('시각 이상(미래)');
});

it('화면에 시각 기준과 국가 열이 나온다', function () {
    AccessLog::create(['actor' => '게스트', 'method' => 'GET', 'path' => '/', 'status' => 200,
        'ip' => '1.0.1.1', 'country' => 'VN', 'created_at' => now()]);

    actingAs($this->admin)
        ->get('/admin/screen/accesslog')
        ->assertOk()
        ->assertSee('접속 국가')
        ->assertSee('베트남')
        ->assertSee('시각 기준')
        // 판별표가 없으니 안내가 떠야 한다.
        ->assertSee('국가 판별표가 없어');
});
