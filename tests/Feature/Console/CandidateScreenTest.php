<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ConsoleController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;

/**
 * 콘솔 업무 화면이 실제로 열리는지 확인한다.
 *
 * 화면 키는 라우트 제약(`[a-z_-]+`)과 ConsoleController::screen() 의 match 양쪽에
 * 걸려 있어, 한쪽만 고치면 404 가 난다. 실제로 하이픈이 빠져 있어
 * [계정 삭제 요청] 화면이 열리지 않던 적이 있다.
 */
function consoleAdmin(): User
{
    test()->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);

    return $admin;
}

it('사이드바에 등록된 모든 화면이 열린다', function () {
    $admin = consoleAdmin();

    $keys = collect(ConsoleController::menu())
        ->flatMap(fn (array $group) => collect($group['items'])->pluck('key'))
        ->all();

    // 사이드바에 없고 상단 버튼으로만 여는 화면도 포함한다.
    $keys[] = 'service-requests';

    foreach ($keys as $key) {
        $this->actingAs($admin)
            ->get(url('admin/screen/'.$key))
            ->assertOk("화면이 열리지 않습니다: {$key}");
    }
})->with([[null]]);
