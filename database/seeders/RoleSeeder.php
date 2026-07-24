<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Shared\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * 역할 6종 생성 (CLAUDE.md §1). 웹 가드 기준.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
