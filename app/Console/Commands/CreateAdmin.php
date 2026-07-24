<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Shared\Enums\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * 운영 콘솔 관리자(ndn_admin) 계정 생성.
 * 운영에서는 시더가 테스트 계정을 만들지 않으므로 이 명령으로 실제 관리자를 만든다.
 *
 *   php artisan ndn:create-admin admin@example.com "홍길동"
 *   php artisan ndn:create-admin admin@example.com "홍길동" --password='S3cure!'
 */
class CreateAdmin extends Command
{
    protected $signature = 'ndn:create-admin
        {email : 관리자 이메일}
        {name : 표시 이름}
        {--password= : 비밀번호(미지정 시 안전한 임시값 자동 생성)}';

    protected $description = 'ndn_admin 역할의 운영 콘솔 관리자 계정을 생성한다';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->argument('name');
        $password = (string) ($this->option('password') ?: Str::password(16));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! UserRole::tryFrom('ndn_admin') || Role::where('name', UserRole::NdnAdmin->value)->doesntExist()) {
            $this->error('ndn_admin 역할이 없습니다. 먼저 `php artisan db:seed --class=RoleSeeder --force` 를 실행하세요.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);
        $user->assignRole(UserRole::NdnAdmin->value);

        $this->info('관리자 계정을 생성했습니다.');
        $this->table(
            ['이메일', '이름', '비밀번호'],
            [[$email, $name, $this->option('password') ? '(입력한 값)' : $password]],
        );

        if (! $this->option('password')) {
            $this->warn('위 임시 비밀번호를 안전하게 보관하고, 로그인 후 즉시 변경하세요.');
        }

        return self::SUCCESS;
    }
}
