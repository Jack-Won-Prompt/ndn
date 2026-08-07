<?php

declare(strict_types=1);

use App\Models\User;
use App\Shared\Enums\UserRole;
use App\Shared\Support\DeployState;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

/**
 * 배포가 덜 끝난 상태를 드러낸다.
 *
 * 한 세션에 같은 원인으로 장애 보고가 세 번 왔다 — 코드만 올라가고 마이그레이션이
 * 돌지 않았다. 증상만 매번 달랐고(사진 안 나옴 / 500 / 404) 원인은 어디에도
 * 드러나지 않았다. 그래서 콘솔이 직접 알리게 했다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
});

it('마이그레이션이 모두 적용됐으면 아무 문제도 보고하지 않는다', function () {
    expect(DeployState::pendingMigrations())->toBe(0);
    expect(DeployState::problems(fresh: true))->toBe([]);
});

it('적용되지 않은 마이그레이션을 개수까지 알아낸다', function () {
    // 배포 중 마이그레이션이 빠진 상황을 그대로 만든다.
    DB::table('migrations')->orderByDesc('id')->limit(3)->delete();

    expect(DeployState::pendingMigrations())->toBe(3);
    expect(DeployState::problems(fresh: true))->toHaveCount(1);
    expect(DeployState::problems(fresh: true)[0])->toContain('3개');
});

it('콘솔에 경고 띠가 뜬다', function () {
    DB::table('migrations')->orderByDesc('id')->limit(2)->delete();
    DeployState::problems(fresh: true);

    actingAs($this->admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('배포가 덜 끝났습니다')
        ->assertSee('php artisan migrate --force');
});

it('사이드바 그룹은 접을 수 있게 그려진다', function () {
    // 6그룹 22항목이라 한 화면에 다 들어가지 않는다.
    actingAs($this->admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('data-group-toggle', false)
        ->assertSee('nav-group__items', false);
});

it('정상 상태에서는 경고 띠가 없다', function () {
    DeployState::problems(fresh: true);

    actingAs($this->admin)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('배포가 덜 끝났습니다');
});

it('배포 점검 명령이 미적용 마이그레이션에서 실패한다', function () {
    DeployState::problems(fresh: true);
    $this->artisan('ndn:deploy-check')->assertSuccessful();

    DB::table('migrations')->orderByDesc('id')->limit(1)->delete();
    DeployState::problems(fresh: true);

    // 배포 스크립트가 이 종료 코드를 보고 멈춘다.
    $this->artisan('ndn:deploy-check')->assertFailed();
});
