<?php

declare(strict_types=1);

use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Worker 개인정보 인프라 검증 (CLAUDE.md §7-1, §7-6).
 *
 * Shared 계층(encrypted cast, blind index, 마스킹, 감사로그)이 실제 모델에서
 * 동작하는지 증명한다.
 */
it('민감 필드는 DB 에 평문이 아니라 암호문으로 저장된다', function () {
    $worker = Worker::factory()->create([
        'passport_no' => 'M1234567',
        'phone_home_country' => '+8801711111111',
    ]);

    // 원시 컬럼 값(cast 우회)을 직접 조회
    $raw = DB::table('workers')->where('id', $worker->id)->first();

    // 평문이 그대로 저장되어 있으면 안 된다
    expect($raw->passport_no)->not->toBe('M1234567')
        ->and($raw->phone_home_country)->not->toBe('+8801711111111');

    // 암호문은 Laravel 의 eyJ... base64 JSON 형태
    expect($raw->passport_no)->toStartWith('eyJ');
});

it('모델로 조회하면 민감 필드가 복호화되어 나온다', function () {
    $worker = Worker::factory()->create(['passport_no' => 'M7654321']);

    $fresh = Worker::find($worker->id);

    expect($fresh->passport_no)->toBe('M7654321');
});

it('blind index 로 여권번호 검색이 된다', function () {
    Worker::factory()->create(['passport_no' => 'A1111111']);
    $target = Worker::factory()->create(['passport_no' => 'B2222222']);
    Worker::factory()->create(['passport_no' => 'C3333333']);

    $found = Worker::wherePassport('B2222222')->get();

    expect($found)->toHaveCount(1)
        ->and($found->first()->id)->toBe($target->id);
});

it('blind index 는 여권번호가 바뀌면 함께 갱신된다', function () {
    $worker = Worker::factory()->create(['passport_no' => 'D4444444']);

    $worker->update(['passport_no' => 'E5555555']);

    expect(Worker::wherePassport('D4444444')->exists())->toBeFalse()
        ->and(Worker::wherePassport('E5555555')->first()?->id)->toBe($worker->id);
});

it('toArray 는 민감 필드를 마스킹한다', function () {
    $worker = Worker::factory()->create([
        'passport_no' => 'M1234567',
        'phone_home_country' => '+8801711111111',
    ]);

    $array = $worker->fresh()->toArray();

    // 앞 1글자만 남고 나머지는 가려진다 ('M1234567' = 8자 → M + 7점)
    expect($array['passport_no'])->toBe('M•••••••')
        ->and($array['passport_no'])->not->toContain('1234567')
        ->and($array['phone_home_country'])->not->toContain('8801711111111');
});

it('개인정보 열람 시 감사 로그가 남는다', function () {
    $admin = User::factory()->create();
    $worker = Worker::factory()->create();

    $worker->recordAccessBy($admin, 'view');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'personal-data-access',
        'subject_type' => Worker::class,
        'subject_id' => $worker->id,
        'causer_id' => $admin->id,
    ]);
})->group('guard');
