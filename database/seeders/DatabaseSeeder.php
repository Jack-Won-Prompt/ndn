<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Shared\Enums\UserRole;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 역할 6종 먼저 (계정에 부여하기 전). 운영·로컬 공통으로 항상 필요.
        $this->call(RoleSeeder::class);

        // 운영(production)에서는 테스트 계정·데모 데이터를 만들지 않는다.
        // 관리자 계정은 별도로 생성한다: php artisan ndn:create-admin ...
        if (app()->environment('production')) {
            return;
        }

        // 로컬/스테이징용 역할별 테스트 계정 (CLAUDE.md §3: 시더에 역할·테스트 계정 포함)
        $accounts = [
            ['NDN 관리자',  'admin@ndn.test',   UserRole::NdnAdmin],
            ['시청 담당자',  'city@ndn.test',    UserRole::CityOfficer],
            ['농가',        'farm@ndn.test',    UserRole::FarmOwner],
            ['송출기관',     'agency@ndn.test',  UserRole::SendingAgency],
            ['제휴 대리점',  'partner@ndn.test', UserRole::PartnerAgency],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => $email,
            ]);
            $user->assignRole($role->value);
        }

        // 내부 시연·검증용 데모 업무 데이터 (로컬 전용)
        $this->call(DemoDataSeeder::class);
    }
}
