<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 저장 타임존 가드 (CLAUDE.md §11 — 날짜는 UTC 저장).
 *
 * PHP 가 넣는 값과 DB 가 채우는 값(DEFAULT current_timestamp, NOW())이
 * 같은 기준이어야 한다. 어긋나면 한 표에 9시간 차이 나는 값이 섞이고,
 * 화면은 둘 다 UTC 로 보고 다시 변환하므로 어떤 행은 9시간, 어떤 행은
 * 18시간 뒤로 보인다. 증상이 행마다 달라 원인을 찾기 어렵다.
 *
 * 실제로 그런 상태였다 — MariaDB 세션 타임존이 SYSTEM(윈도 KST)이었고
 * `sos_alerts.alerted_at` 에 DEFAULT current_timestamp() 가 걸려 있었다.
 * 앱 코드가 늘 now() 를 넘겨 터지지 않았을 뿐인 함정이었다.
 */
it('앱 타임존이 UTC 다', function () {
    // 이게 흔들리면 아래 검사들이 의미를 잃는다.
    expect(config('app.timezone'))->toBe('UTC');
    expect(now()->timezoneName)->toBe('UTC');
});

it('mysql·mariadb 커넥션에 세션 타임존이 박혀 있다', function () {
    // 테스트는 sqlite 로 돌아 아래 런타임 검사가 건너뛰어진다. 그래서 설정
    // 자체를 본다 — 이 줄이 지워지는 것이 실제로 일어났던 회귀다.
    foreach (['mysql', 'mariadb'] as $connection) {
        $tz = config("database.connections.{$connection}.timezone");

        expect($tz)->not->toBeNull("{$connection} 커넥션에 timezone 이 없다");
        // 이름 있는 지역은 MySQL 시간대 표가 있어야 먹는다. 숫자 오프셋만 쓴다.
        expect($tz)->toMatch('/^[+-]\d{2}:\d{2}$/');
        expect(now()->setTimezone($tz)->format('H:i'))->toBe(now()->format('H:i'));
    }
});

it('DB 세션 타임존이 앱과 같다', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        // sqlite 에는 세션 타임존이 없다. 시각은 PHP 가 만들어 넣는다.
        expect(true)->toBeTrue();

        return;
    }

    $dbNow = DB::selectOne('SELECT NOW() AS n')->n;

    // 초 단위 오차만 허용한다. 9시간씩 벌어지는 게 잡으려는 상황이다.
    expect(abs(strtotime((string) $dbNow) - now()->timestamp))->toBeLessThan(5);
});

it('DB 가 채우는 기본값도 앱과 같은 기준이다', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        expect(true)->toBeTrue();

        return;
    }

    // DEFAULT current_timestamp 로 들어가는 값을 실제로 재 본다.
    // sos_alerts.alerted_at 이 이 방식이라 남 얘기가 아니다.
    Schema::create('tz_guard_probe', function ($table) {
        $table->id();
        $table->timestamp('at')->useCurrent();
    });

    try {
        DB::table('tz_guard_probe')->insert([]);
        $at = DB::table('tz_guard_probe')->value('at');

        expect(abs(strtotime((string) $at) - now()->timestamp))->toBeLessThan(5);
    } finally {
        Schema::dropIfExists('tz_guard_probe');
    }
});
