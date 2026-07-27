<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Arrival\Enums\ArrivalDocument;
use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 테스트 계정 — 웹 포털 역할별 5종 + 근로자 앱 4종.
 *
 * 로컬에서는 DatabaseSeeder 가 자동 호출한다. 운영에서 필요하면 명시적으로만 실행한다:
 *   php artisan db:seed --class=Database\\Seeders\\TestAccountsSeeder --force
 *
 * updateOrCreate 라 여러 번 실행해도 안전하며, **매번 계정 상태를 정상으로 되돌린다**.
 * 근로자 계정이 승인 대기(pending)나 거절 상태로 남아 로그인이 막힌 경우를 복구하려면
 * 다시 실행하면 된다.
 *
 * ※ 비밀번호는 config('ndn.test_account_password') 이고 기본값은 'password' 다.
 *   외부에서 접근 가능한 서버에 시드할 때는 .env 의 NDN_TEST_ACCOUNT_PASSWORD 로
 *   반드시 덮어쓰고, 실제 서비스 오픈 전에는 이 계정들을 제거할 것.
 */
class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('ndn.test_account_password');

        $admin = $this->seedPortalAccounts($password);
        $this->linkPortalOrganisations();
        $this->seedWorkerAppAccounts($password, $admin);

        $this->command?->warn(
            '테스트 계정입니다. 서비스 오픈 전 반드시 제거하세요.'
        );
    }

    /** 웹 포털 역할별 계정 5종. NDN 관리자 User 를 돌려준다(근로자 승인자로 사용). */
    private function seedPortalAccounts(string $password): User
    {
        $accounts = [
            ['NDN 관리자',  'admin@ndn.test',   UserRole::NdnAdmin],
            ['시청 담당자',  'city@ndn.test',    UserRole::CityOfficer],
            ['농가',        'farm@ndn.test',    UserRole::FarmOwner],
            ['송출기관',     'agency@ndn.test',  UserRole::SendingAgency],
            ['제휴 대리점',  'partner@ndn.test', UserRole::PartnerAgency],
        ];

        $admin = null;

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ],
            );

            if (! $user->hasRole($role->value)) {
                $user->assignRole($role->value);
            }

            if ($role === UserRole::NdnAdmin) {
                $admin = $user;
            }
        }

        $this->command?->info('웹 포털 계정 5종 준비 완료 (admin/city/farm/agency/partner@ndn.test)');

        return $admin;
    }

    /**
     * 시청·농가 계정을 실제 조직에 연결한다.
     *
     * 관리자 앱의 역할별 스코프(PortalScope)는 시청은 users.city_id, 농가는
     * farms.owner_user_id 를 기준으로 동작한다. 이 연결이 없으면 두 역할은
     * 로그인은 되지만 목록이 항상 비어 보인다.
     */
    private function linkPortalOrganisations(): void
    {
        $city = City::firstOrCreate(
            ['name' => '테스트시'],
            ['region' => '테스트도'],
        );

        $officer = User::where('email', 'city@ndn.test')->first();
        $officer?->update(['city_id' => $city->id]);

        $farmOwner = User::where('email', 'farm@ndn.test')->first();

        if ($farmOwner !== null) {
            Farm::firstOrCreate(
                ['name' => '테스트 농가'],
                [
                    'owner_user_id' => $farmOwner->id,
                    'city_id' => $city->id,
                    'main_crop' => '딸기',
                ],
            )->update([
                // 이미 있던 농가라면 소유자·지자체를 현재 테스트 계정으로 맞춰 준다
                'owner_user_id' => $farmOwner->id,
                'city_id' => $city->id,
            ]);
        }

        $this->command?->info('시청·농가 계정을 테스트시/테스트 농가에 연결했습니다.');
    }

    /**
     * 근로자 앱(모바일) 로그인용 계정 — 송출 4개국 × 언어별 1명 (CLAUDE.md §6, §9).
     * 앱의 다국어 화면을 언어마다 실제로 확인할 수 있게 국적별로 하나씩 만든다.
     *
     * 가입 승인제이므로 승인 완료(active) 상태로 만들어야 앱에서 바로 로그인된다.
     */
    private function seedWorkerAppAccounts(string $password, ?User $admin): void
    {
        $workers = [
            ['Nguyen Van An', 'worker.vn@ndn.test', 'VN', 'vi', 'C1234567'],
            ['Md. Rahman', 'worker.bd@ndn.test', 'BD', 'bn', 'BW1234567'],
            ['Somchai Vong', 'worker.la@ndn.test', 'LA', 'lo', 'P1234567'],
            ['Nuwan Perera', 'worker.lk@ndn.test', 'LK', 'si', 'N1234567'],
        ];

        $farm = Farm::where('name', '테스트 농가')->first();

        foreach ($workers as [$name, $email, $nationality, $locale, $passport]) {
            $worker = Worker::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,          // hashed cast 가 해시 처리
                    'nationality' => $nationality,
                    'locale' => $locale,
                    'passport_no' => $passport,       // encrypted cast + blind index
                    'status' => WorkerStatus::Active, // 승인 완료 — 앱 로그인 가능
                    'approved_at' => now(),
                    'approved_by' => $admin?->id,
                ],
            );

            // 테스트 농가에 배정해 둔다 — 이게 있어야 시청·농가 계정에도 보이고,
            // 입국 관리 화면에 진행할 건이 생긴다.
            if ($farm !== null) {
                $placement = Placement::updateOrCreate(
                    ['worker_id' => $worker->id, 'farm_id' => $farm->id],
                    [
                        'status' => PlacementStatus::Confirmed,
                        'confirmed_at' => now(),
                        'confirmed_by' => $admin?->id,
                        'start_date' => now()->addWeeks(2)->toDateString(),
                        'end_date' => now()->addMonths(5)->toDateString(),
                    ],
                );

                ArrivalRecord::firstOrCreate(
                    ['placement_id' => $placement->id],
                    [
                        'status' => ArrivalStatus::Scheduled,
                        'airport' => '인천(ICN)',
                        'scheduled_arrival_at' => now()->addWeeks(2),
                        'documents' => ArrivalDocument::emptyChecklist(),
                    ],
                );
            }
        }

        $this->command?->info('근로자 앱 계정 4종 준비 완료 (worker.{vn,bd,la,lk}@ndn.test · 승인 완료)');

        if ($farm !== null) {
            $this->command?->info('테스트 농가 배정 + 입국 예정 기록 4건 생성');
        }
    }
}
