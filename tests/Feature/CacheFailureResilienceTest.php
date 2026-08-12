<?php

declare(strict_types=1);

use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\UserRole;
use App\Shared\Support\DeployState;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkerGuideSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;

/**
 * 캐시 저장소가 죽어도 화면은 살아야 한다.
 *
 * 운영에서 실제로 겪은 일이다 — 캐시 디렉터리에 쓰기 권한이 없어
 * `file_put_contents(... /storage/framework/cache/data/...): Permission denied`
 * 가 났다. 그때 배포 이상을 **알리라고 넣은 코드**(DeployState)가 캐시를
 * 무방비로 써서 콘솔을 통째로 500 으로 만들었다.
 *
 * 캐시는 빠르라고 두는 것이지 없으면 못 도는 물건이 아니다.
 */
/** 쓰기가 막힌 캐시 저장소를 흉내 낸다. */
function breakCache(): void
{
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('Permission denied'));
    Cache::shouldReceive('put')->andThrow(new RuntimeException('Permission denied'));
    Cache::shouldReceive('get')->andThrow(new RuntimeException('Permission denied'));
    Cache::shouldReceive('forget')->andThrow(new RuntimeException('Permission denied'));
}

it('캐시가 죽어도 배포 상태 판정이 살아 있다', function () {
    breakCache();

    $problems = DeployState::problems();

    // 판정 자체는 되고, 캐시를 못 쓴다는 사실도 함께 알린다.
    expect($problems)->toContain(DeployState::CACHE_PROBLEM);
});

it('캐시가 죽어도 콘솔이 열린다', function () {
    // 이게 핵심이다. 예전에는 여기서 500 이 났다.
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);

    breakCache();

    actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertSee('캐시 저장소에 쓸 수 없습니다');
});

it('캐시가 죽어도 근로자 안내 자료가 나온다', function () {
    // 근로자가 보는 화면이라 느려질지언정 빈 화면이나 오류를 주면 안 된다.
    $this->seed(WorkerGuideSeeder::class);
    Sanctum::actingAs(Worker::factory()->create(['locale' => 'ko']), ['*']);

    breakCache();

    $this->getJson('/api/v1/guides/pre-training')
        ->assertOk()
        ->assertJsonPath('data.key', 'pre-training');
});

it('캐시가 정상이면 문제로 잡지 않는다', function () {
    expect(DeployState::cacheWritable())->toBeTrue();
    expect(DeployState::problems(fresh: true))->not->toContain(DeployState::CACHE_PROBLEM);
});

it('배포 점검 명령이 캐시 이상을 잡아낸다', function () {
    breakCache();

    $this->artisan('ndn:deploy-check')
        ->expectsOutputToContain('캐시 저장소에 쓸 수 없습니다')
        ->assertFailed();
});
