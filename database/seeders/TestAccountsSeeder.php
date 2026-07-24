<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Shared\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 역할별 테스트 계정 5종 (비밀번호 공통: password).
 *
 * 로컬에서는 DatabaseSeeder 가 자동 호출한다. 운영에서 테스트가 필요하면
 * 명시적으로만 실행한다:
 *   php artisan db:seed --class=Database\\Seeders\\TestAccountsSeeder --force
 *
 * firstOrCreate 로 멱등하게 동작하므로 여러 번 실행해도 중복/오류가 없다.
 * ※ 실제 운영 오픈 전에는 이 계정들을 반드시 제거하세요.
 */
class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['NDN 관리자',  'admin@ndn.test',   UserRole::NdnAdmin],
            ['시청 담당자',  'city@ndn.test',    UserRole::CityOfficer],
            ['농가',        'farm@ndn.test',    UserRole::FarmOwner],
            ['송출기관',     'agency@ndn.test',  UserRole::SendingAgency],
            ['제휴 대리점',  'partner@ndn.test', UserRole::PartnerAgency],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            if (! $user->hasRole($role->value)) {
                $user->assignRole($role->value);
            }
        }

        $this->command?->info('테스트 계정 5종 준비 완료 (비밀번호: password)');
    }
}
