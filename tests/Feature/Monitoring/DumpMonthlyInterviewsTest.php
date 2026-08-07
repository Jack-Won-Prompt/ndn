<?php

declare(strict_types=1);

use App\Console\Commands\DumpMonthlyInterviews;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 월별 인터뷰 폐기 전 덤프 (되돌릴 수 없는 작업의 마지막 안전장치).
 *
 * 폐기 마이그레이션이 표를 지우기 직전에 이 명령을 부른다. 운영에서 사고가 나면
 * 덤프 파일이 유일한 근거이므로, 표가 없어진 뒤에도 명령이 깨지지 않아야 한다.
 */
function scratchDumpPath(string $name): string
{
    return storage_path('framework/testing/'.$name);
}

afterEach(function () {
    foreach (glob(storage_path('framework/testing/dump-test-*.json')) ?: [] as $file) {
        @unlink($file);
    }
    Schema::dropIfExists('monthly_interviews');
});

it('표가 있으면 행을 그대로 파일로 남긴다', function () {
    Schema::create('monthly_interviews', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('worker_id');
        $table->date('interviewed_on');
        $table->string('risk_level', 10);
        $table->text('memo')->nullable();
    });

    DB::table('monthly_interviews')->insert([
        ['worker_id' => 7, 'interviewed_on' => '2026-07-05', 'risk_level' => 'high', 'memo' => '급여 지연'],
        ['worker_id' => 9, 'interviewed_on' => '2026-07-06', 'risk_level' => 'low', 'memo' => null],
    ]);

    $path = scratchDumpPath('dump-test-a.json');
    expect(DumpMonthlyInterviews::dump($path))->toBe($path);

    $dump = json_decode((string) file_get_contents($path), true);

    expect($dump['table'])->toBe('monthly_interviews')
        ->and($dump['count'])->toBe(2)
        ->and($dump['rows'][0]['worker_id'])->toBe(7)
        ->and($dump['rows'][0]['memo'])->toBe('급여 지연');
});

it('행이 없어도 파일은 남긴다 — 비어 있었다는 것도 기록이다', function () {
    Schema::create('monthly_interviews', function (Blueprint $table) {
        $table->id();
    });

    $path = scratchDumpPath('dump-test-b.json');
    DumpMonthlyInterviews::dump($path);

    expect(json_decode((string) file_get_contents($path), true)['count'])->toBe(0);
});

it('표가 이미 없으면 조용히 넘어간다', function () {
    // 폐기 마이그레이션이 돌고 난 뒤의 상태. 명령이 깨지면 안 된다.
    expect(Schema::hasTable('monthly_interviews'))->toBeFalse();

    expect(DumpMonthlyInterviews::dump(scratchDumpPath('dump-test-c.json')))->toBeNull();

    $this->artisan('monthly-interviews:dump')
        ->expectsOutputToContain('덤프할 것이 없습니다')
        ->assertSuccessful();
});

it('월별 인터뷰 표가 폐기됐다', function () {
    // 대체한 곳이 제자리에 있는지까지 확인한다 — 지우기만 하고 대체가 없으면
    // 근로자 상태를 남길 데가 사라진다.
    expect(Schema::hasTable('monthly_interviews'))->toBeFalse()
        ->and(Schema::hasTable('life_checklist_checks'))->toBeTrue()
        ->and(Schema::hasTable('work_reviews'))->toBeTrue();
});
