<?php

declare(strict_types=1);

use App\Domains\Recruitment\Models\Worker;
use Illuminate\Support\Facades\DB;

/**
 * 파기 스케줄 잡 (CLAUDE.md §7-7).
 */
it('soft delete 후 90일 경과분의 민감 필드를 파기한다', function () {
    // 91일 전 삭제된 근로자
    $old = Worker::factory()->create(['passport_no' => 'M1234567']);
    $old->delete();
    $old->forceFill(['deleted_at' => now()->subDays(91)])->saveQuietly();

    // 최근 삭제된 근로자 (파기 대상 아님)
    $recent = Worker::factory()->create(['passport_no' => 'B7654321']);
    $recent->delete();
    $recent->forceFill(['deleted_at' => now()->subDays(10)])->saveQuietly();

    $this->artisan('workers:purge-expired')->assertSuccessful();

    $oldRaw = DB::table('workers')->where('id', $old->id)->first();
    $recentRaw = DB::table('workers')->where('id', $recent->id)->first();

    // 91일 경과분: 민감 필드·blind index 파기
    expect($oldRaw->passport_no)->toBeNull()
        ->and($oldRaw->passport_no_bidx)->toBeNull();

    // 레코드 자체는 이력 보존 (삭제 안 됨)
    expect($oldRaw)->not->toBeNull();

    // 최근 삭제분은 그대로
    expect($recentRaw->passport_no)->not->toBeNull();
});

it('dry-run 은 실제 파기하지 않는다', function () {
    $old = Worker::factory()->create(['passport_no' => 'M1111111']);
    $old->delete();
    $old->forceFill(['deleted_at' => now()->subDays(100)])->saveQuietly();

    $this->artisan('workers:purge-expired --dry-run')->assertSuccessful();

    expect(DB::table('workers')->where('id', $old->id)->value('passport_no'))->not->toBeNull();
});
