<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 역할 6종 먼저 (계정에 부여하기 전). 운영·로컬 공통으로 항상 필요.
        $this->call(RoleSeeder::class);

        // 운영(production)에서는 테스트 계정·데모 데이터를 만들지 않는다.
        // 관리자 계정은 별도로 생성한다: php artisan ndn:create-admin ...
        // 운영에서 테스트가 필요하면 아래 시더를 명시적으로 실행:
        //   php artisan db:seed --class=Database\\Seeders\\TestAccountsSeeder --force
        //   php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder --force
        if (app()->environment('production')) {
            return;
        }

        // 로컬/스테이징: 역할별 테스트 계정 + 데모 업무 데이터
        $this->call(TestAccountsSeeder::class);
        $this->call(DemoDataSeeder::class);
    }
}
