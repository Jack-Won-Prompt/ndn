<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 위치정보 컬럼 가드 (CLAUDE.md §7-2, §10).
 *
 * 근로자 위치(lat/lng)는 오직 두 테이블에만 존재할 수 있다:
 *   - inspection_checkins (점검자 방문 좌표)
 *   - sos_alerts          (SOS 발신 순간 좌표)
 * 그 외 어떤 테이블에도 위치 컬럼이 생기면 이 테스트가 실패한다.
 *
 * 절대 삭제 금지 가드 테스트.
 */
it('허용된 두 테이블 외에는 위치 컬럼이 존재하지 않는다', function () {
    $allowedTables = ['inspection_checkins', 'sos_alerts'];

    // 위치로 간주하는 컬럼명 패턴
    $locationColumns = ['lat', 'lng', 'latitude', 'longitude', 'geo_lat', 'geo_lng'];

    $driver = DB::connection()->getDriverName();

    // 현재 스키마의 전체 테이블 목록
    $tables = collect(Schema::getTables())
        ->pluck('name')
        ->reject(fn (string $t) => str_starts_with($t, 'sqlite_'));

    $violations = [];

    foreach ($tables as $table) {
        $columns = Schema::getColumnListing($table);

        foreach ($columns as $column) {
            $isLocation = in_array(strtolower($column), $locationColumns, true);

            if ($isLocation && ! in_array($table, $allowedTables, true)) {
                $violations[] = "{$table}.{$column}";
            }
        }
    }

    expect($violations)->toBeEmpty(
        '허용되지 않은 위치 컬럼이 발견되었습니다: '.implode(', ', $violations)
    );
})->group('guard');

it('허용된 두 테이블에는 위치 컬럼이 실제로 존재한다 (화이트리스트가 살아있는지)', function () {
    // 이 단언이 있어야 위 가드가 "위치 컬럼이 아예 없어서 통과"하는 공허한 검사가
    // 되지 않는다. 두 테이블은 반드시 lat/lng 를 가져야 한다.
    foreach (['inspection_checkins', 'sos_alerts'] as $table) {
        $columns = Schema::getColumnListing($table);
        expect($columns)->toContain('lat')
            ->and($columns)->toContain('lng');
    }
})->group('guard');
