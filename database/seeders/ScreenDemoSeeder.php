<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Models\Placement;
use App\Domains\Monitoring\Actions\RecordWorkReviewAction;
use App\Domains\Monitoring\Enums\FarmVisitStatus;
use App\Domains\Monitoring\Enums\WorkReviewResult;
use App\Domains\Monitoring\Enums\WorkReviewType;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Monitoring\Models\LifeChecklistCheck;
use App\Domains\Monitoring\Models\LifeChecklistItem;
use App\Domains\Monitoring\Models\WorkReviewItem;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Support\Enums\SosStatus;
use App\Domains\Support\Models\SosAlert;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\ChatService;
use App\Models\Invitation;
use App\Models\User;
use App\Shared\Enums\UserRole;
use App\Shared\Translation\GoogleTranslator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 로그인(역할)별 모든 화면 테스트 데이터 — 화면당 10건 이상 + 관련 파일(전자서명·농가 현장사진·채팅 첨부).
 *
 * 운영에서도 명시 실행 (TestAccountsSeeder 먼저 실행 권장):
 *   php artisan db:seed --class=Database\\Seeders\\TestAccountsSeeder --force
 *   php artisan db:seed --class=Database\\Seeders\\ScreenDemoSeeder --force
 *
 * 역할 계정(농가·시청·대리점)을 실제 소속(농가 소유·시 소속·대리점 배정)에 연결해
 * 각 로그인이 자기 화면에서 실제 데이터를 보도록 만든다. 개인정보는 팩토리의 암호화
 * 경로로 저장되고 위치정보는 SOS 한 곳에만 저장된다(§7).
 */
class ScreenDemoSeeder extends Seeder
{
    private const N = 10;

    private array $counts = [];

    private array $files = ['signature' => 0, 'farm_photo' => 0, 'chat_file' => 0];

    public function run(): void
    {
        $admin = User::role(UserRole::NdnAdmin->value)->first();

        // ── 역할 계정 ↔ 소속 연결 (각 로그인이 자기 데이터를 보도록) ──
        [$cities, $farms] = $this->baseInfo();
        $ownFarm = $this->linkRoleAccounts($cities, $farms);
        $partnerAgencyId = 1;

        // ── 근로자: 활성 12 + 승인대기 10(가입 승인 화면) ──
        $workers = Worker::factory()->count(12)->create();
        $this->counts['근로자(활성)'] = $workers->count();
        $pending = Worker::factory()->count(self::N)->create(['status' => WorkerStatus::Pending->value]);
        $this->counts['가입 승인(대기 근로자)'] = $pending->count();

        // ── 배정 확정 12 (농가↔근로자, 농가 소유 계정 농가 포함) ──
        $placements = collect();
        $workers->take(12)->each(function (Worker $w, int $i) use ($farms, $ownFarm, $placements) {
            $farm = $i < 3 ? $ownFarm : $farms->random();
            $placements->push(Placement::factory()->confirmed()->create([
                'worker_id' => $w->id, 'farm_id' => $farm->id,
            ]));
        });
        $this->counts['배정(확정)'] = $placements->count();

        // ── 입국·이송 10 (확정 배정 기반) ──
        $placements->take(self::N)->each(fn (Placement $p) => ArrivalRecord::factory()->create(['placement_id' => $p->id]));
        $this->counts['입국·이송'] = self::N;

        // ── 수요 신청 10 (농가 소유 계정 것 + 시청 관할 것 포함) ──
        collect(range(1, self::N))->each(function (int $i) use ($ownFarm, $farms, $cities) {
            DemandRequest::factory()->create([
                'farm_id' => $i <= 3 ? $ownFarm->id : $farms->random()->id,
                'city_id' => $cities->random()->id,
                'status' => fake()->randomElement([DemandStatus::Draft, DemandStatus::Submitted, DemandStatus::Aggregated, DemandStatus::LetterIssued]),
                'submitted_at' => now()->subDays(fake()->numberBetween(1, 40)),
            ]);
        });
        $this->counts['수요 신청'] = self::N;

        // ── 후보자 10 ──
        collect(range(1, self::N))->each(fn () => Candidate::factory()->create([
            'status' => fake()->randomElement([CandidateStatus::Applied, CandidateStatus::Passed, CandidateStatus::Held, CandidateStatus::Rejected]),
        ]));
        $this->counts['후보자·평가'] = self::N;

        // ── 온보딩 검수 10 + 전자서명 파일 ──
        $workers->take(self::N)->each(function (Worker $w) use ($cities) {
            OnboardingSubmission::factory()->submitted()->create([
                'worker_id' => $w->id,
                'status' => fake()->randomElement([OnboardingStatus::Submitted, OnboardingStatus::UnderReview, OnboardingStatus::Approved]),
                'payload' => ['address_kr' => $cities->random()->name.' '.fake()->buildingNumber().'번지', 'emergency_contact' => fake('ko_KR')->name().' / '.fake()->numerify('010-####-####')],
                'signature_path' => $this->makeSignature($w->id),
            ]);
        });
        $this->counts['온보딩 검수'] = self::N;

        // ── 정착 처리보드 12 (일부 대리점 배정 → 대리점 로그인) ──
        $workers->random(min(12, $workers->count()))->values()->each(function (Worker $w, int $i) use ($partnerAgencyId) {
            SettlementRequest::factory()->create([
                'worker_id' => $w->id,
                'status' => fake()->randomElement(SettlementStatus::cases()),
                'assigned_agency_id' => $i < 4 ? $partnerAgencyId : null,
            ]);
        });
        $this->counts['정착 처리보드'] = 12;

        // ── 생활 체크리스트 10 (근로자 본인이 체크한 것) ──
        $checklist = LifeChecklistItem::query()->active()->get();
        $workers->take(self::N)->each(function (Worker $w) use ($checklist) {
            foreach ($checklist->random(fake()->numberBetween(0, $checklist->count())) as $item) {
                LifeChecklistCheck::create([
                    'worker_id' => $w->id,
                    'life_checklist_item_id' => $item->id,
                    'checked_at' => now()->subDays(fake()->numberBetween(0, 20)),
                ]);
            }
        });
        $this->counts['생활 체크리스트'] = self::N;

        // ── 민원 10 ──
        $workers->random(min(self::N, $workers->count()))->each(fn (Worker $w) => SupportTicket::factory()->create(['worker_id' => $w->id]));
        $this->counts['민원'] = self::N;

        // ── SOS 10 (§7-2: 좌표는 여기에만) ──
        $workers->random(min(self::N, $workers->count()))->each(fn (Worker $w) => SosAlert::create([
            'worker_id' => $w->id,
            'lat' => fake()->randomFloat(6, 34.5, 37.9),
            'lng' => fake()->randomFloat(6, 126.5, 129.2),
            'alerted_at' => now()->subDays(fake()->numberBetween(0, 20))->subHours(fake()->numberBetween(0, 23)),
            'status' => fake()->randomElement(SosStatus::cases())->value,
        ]));
        $this->counts['긴급 SOS'] = self::N;

        // ── 농가 방문 점검 10 + 현장사진 + 근로자별 인터뷰 ──
        $this->seedFarmVisits($placements, $admin);

        // ── 조직 초대 10 (상태 분산) ──
        $this->seedInvitations($admin, $partnerAgencyId);

        // ── 채팅 8 (자동번역) + 첨부 파일 ──
        $this->seedChat($workers, $ownFarm);

        $this->report();
    }

    /** 지자체·농가 기준정보 확보. */
    private function baseInfo(): array
    {
        $cities = City::count() >= 6 ? City::inRandomOrder()->take(8)->get() : City::factory()->count(8)->create();
        $farms = Farm::count() >= 12
            ? Farm::inRandomOrder()->take(12)->get()
            : Farm::factory()->count(12)->create(['city_id' => fn () => $cities->random()->id]);
        $this->counts['기준정보(지자체)'] = $cities->count();
        $this->counts['기준정보(농가)'] = $farms->count();

        return [$cities, $farms];
    }

    /** 역할 계정(농가·시청·대리점)을 실제 소속에 연결. 농가 소유 계정의 농가를 반환. */
    private function linkRoleAccounts($cities, $farms): Farm
    {
        $farmUser = User::where('email', 'farm@ndn.test')->first();
        $cityUser = User::where('email', 'city@ndn.test')->first();
        $partnerUser = User::where('email', 'partner@ndn.test')->first();

        $ownFarm = $farmUser
            ? ($farmUser->farms()->first() ?? Farm::factory()->create(['owner_user_id' => $farmUser->id, 'city_id' => $cities->random()->id, 'name' => '테스트 농가']))
            : $farms->first();

        if ($cityUser && $cityUser->city_id === null) {
            $cityUser->forceFill(['city_id' => $ownFarm->city_id ?? $cities->first()->id])->save();
        }
        if ($partnerUser && $partnerUser->assigned_agency_id === null) {
            $partnerUser->forceFill(['assigned_agency_id' => 1])->save();
        }

        return $ownFarm;
    }

    /** 농가 방문 점검 10 + 현장사진(2장) + 그 농가 배정 근로자 인터뷰. */
    private function seedFarmVisits($placements, ?User $admin): void
    {
        $byFarm = $placements->groupBy('farm_id');
        $farmIds = $byFarm->keys()->take(self::N)->values();
        // 부족하면 아무 농가로 채운다
        while ($farmIds->count() < self::N) {
            $farmIds->push(Farm::inRandomOrder()->value('id'));
        }

        $review = app(RecordWorkReviewAction::class);
        $items = WorkReviewItem::query()->active()->get();

        foreach ($farmIds->take(self::N) as $farmId) {
            $visit = FarmVisit::create([
                'farm_id' => $farmId,
                'visited_by' => $admin?->id,
                'visited_on' => now()->subDays(fake()->numberBetween(1, 60))->toDateString(),
                'farm_status' => fake()->randomElement(FarmVisitStatus::cases())->value,
                'worker_status' => fake()->randomElement(FarmVisitStatus::cases())->value,
                'worker_headcount' => fake()->numberBetween(1, 12),
                'work_note' => fake()->boolean(70) ? '근태 양호, 초과근무 일부 발생.' : null,
                'issue_note' => fake()->boolean(40) ? fake()->randomElement(['기숙사 난방 점검 필요', '통역 지원 요청', '급여 지급일 문의']) : null,
                'action_note' => fake()->boolean(30) ? '담당자 후속 조치 예정.' : null,
                'memo' => fake()->boolean(50) ? '전반적으로 안정적.' : null,
            ]);
            // 현장 사진 2장
            foreach (range(1, 2) as $n) {
                $path = $this->storePng("farm-visits/{$visit->id}", ['SITE', $n], 90 + $n * 30, 140, 60);
                $visit->photos()->create(['path' => $path, 'original_name' => "현장{$n}.png", 'size' => Storage::disk('local')->size($path), 'mime' => 'image/png', 'created_at' => now()]);
                $this->files['farm_photo']++;
            }
            // 그 농가 배정 근로자별 근무상태 점검표 (farm_visit_id 로 이 방문에 묶는다)
            foreach (($byFarm[$farmId] ?? collect()) as $p) {
                $answers = $items->mapWithKeys(fn (WorkReviewItem $i) => [
                    $i->id => $i->section->isRating()
                        ? fake()->randomElement(['high', 'high', 'high', 'mid', 'low'])
                        : ($i->adverse ? fake()->randomElement(['no', 'no', 'no', 'yes']) : fake()->randomElement(['yes', 'yes', 'yes', 'no'])),
                ])->all();

                $review->execute($p->worker, $admin ?? User::first(), [
                    'farm_id' => $farmId,
                    'farm_visit_id' => $visit->id,
                    'reviewed_at' => $visit->visited_on->toDateTimeString(),
                    'review_type' => WorkReviewType::Regular->value,
                    'result' => fake()->randomElement([
                        WorkReviewResult::Good->value, WorkReviewResult::Good->value,
                        WorkReviewResult::Fair->value, WorkReviewResult::NeedsImprovement->value,
                    ]),
                    'notable' => fake()->boolean(30) ? '특이사항 확인' : null,
                ], $answers);
            }
        }
        $this->counts['농가 방문 점검'] = self::N;
    }

    /** 조직 초대 10 (대기·수락·만료·철회 분산). */
    private function seedInvitations(?User $admin, int $partnerAgencyId): void
    {
        $roles = [UserRole::CityOfficer, UserRole::FarmOwner, UserRole::SendingAgency, UserRole::PartnerAgency];
        foreach (range(1, self::N) as $i) {
            $role = $roles[$i % count($roles)];
            $inv = [
                'email' => 'invitee'.$i.'_'.Str::lower(Str::random(4)).'@example.com',
                'name' => fake('ko_KR')->name(),
                'role' => $role->value,
                'assigned_agency_id' => $role === UserRole::PartnerAgency ? $partnerAgencyId : null,
                'token' => Invitation::hashToken(bin2hex(random_bytes(20))),
                'invited_by' => $admin?->id,
                'expires_at' => now()->addDays(7),
            ];
            // 상태 분산: 1~5 대기, 6~7 수락, 8 만료, 9~10 철회
            if ($i >= 6 && $i <= 7) {
                $inv['accepted_at'] = now()->subDays($i);
            } elseif ($i === 8) {
                $inv['expires_at'] = now()->subDays(3);
            } elseif ($i >= 9) {
                $inv['revoked_at'] = now()->subDays($i);
            }
            Invitation::create($inv);
        }
        $this->counts['조직 초대'] = self::N;
    }

    /** 채팅 8건 (NDN↔근로자 자동번역, 농가↔근로자) + 이미지 첨부 2건. */
    private function seedChat($workers, Farm $ownFarm): void
    {
        $svc = app(ChatService::class);
        $made = 0;
        foreach ($workers->take(8) as $idx => $w) {
            try {
                $wp = $svc->partyForWorker($w);
                $conv = $svc->resolveConversation(['ndn', null, 'ko'], $wp);
                $svc->send($conv, ['ndn', null, 'ko'], '안녕하세요. 온보딩 서류가 확인되었습니다. 배정 일정을 안내드리겠습니다.');
                $svc->send($conv, $wp, GoogleTranslator::translate('감사합니다. 언제 입국하나요?', $wp[2], 'ko'));

                // 앞 2건은 관리자 측 이미지 첨부(현장/서류 사진)
                if ($idx < 2) {
                    $side = $conv->sideOf('ndn', null);
                    $path = $this->storePng("chat/{$conv->id}", ['DOC', $w->id], 60, 200, 90);
                    $conv->messages()->create([
                        'sender_side' => $side, 'body' => '관련 서류 사진 첨부합니다.', 'body_lang' => 'ko',
                        'file_path' => $path, 'file_name' => '안내문.png',
                        'file_size' => Storage::disk('local')->size($path), 'file_mime' => 'image/png',
                    ]);
                    $conv->forceFill(['last_message_at' => now()])->save();
                    $this->files['chat_file']++;
                }
                $made++;
            } catch (\Throwable $e) {
                $this->command?->warn('채팅 생성 일부 실패: '.$e->getMessage());
            }
        }
        $this->counts['채팅(대화)'] = $made;
    }

    /** 전자서명 PNG (private). */
    private function makeSignature(int $workerId): string
    {
        $img = imagecreatetruecolor(400, 150);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $ink = imagecolorallocate($img, 20, 30, 50);
        imagesetthickness($img, 3);
        $px = 40;
        $py = 90;
        for ($x = 40; $x < 360; $x += 6) {
            $y = 90 + (int) (35 * sin($x / 17 + $workerId));
            imageline($img, $px, $py, $x, $y, $ink);
            $px = $x;
            $py = $y;
        }
        $path = 'onboarding/signatures/'.$workerId.'_'.Str::uuid()->toString().'.png';
        Storage::disk('local')->put($path, $this->pngOut($img));
        $this->files['signature']++;

        return $path;
    }

    /** 단색 배경 + 라벨 PNG 를 지정 디렉터리에 저장하고 경로 반환. */
    private function storePng(string $dir, array $label, int $hue, int $w = 160, int $h = 120): string
    {
        $img = imagecreatetruecolor($w, $h);
        [$r, $g, $b] = $this->hueRgb($hue);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        $white = imagecolorallocate($img, 255, 255, 255);
        imagestring($img, 5, 10, (int) ($h / 2) - 8, implode(' ', $label), $white);
        $path = $dir.'/'.Str::uuid()->toString().'.png';
        Storage::disk('local')->put($path, $this->pngOut($img));

        return $path;
    }

    private function pngOut($img): string
    {
        ob_start();
        imagepng($img);
        $out = (string) ob_get_clean();
        imagedestroy($img);

        return $out;
    }

    private function hueRgb(int $hue): array
    {
        $hue %= 360;
        $x = (1 - abs(($hue / 60) % 2 - 1)) * 200;
        $c = 200;
        [$r, $g, $b] = match (true) {
            $hue < 60 => [$c, $x, 0], $hue < 120 => [$x, $c, 0], $hue < 180 => [0, $c, $x],
            $hue < 240 => [0, $x, $c], $hue < 300 => [$x, 0, $c], default => [$c, 0, $x],
        };

        return [(int) $r + 30, (int) $g + 30, (int) $b + 30];
    }

    private function report(): void
    {
        $this->command?->newLine();
        $this->command?->info('로그인별 모든 화면 테스트 데이터 생성 완료');
        foreach ($this->counts as $label => $n) {
            $this->command?->line(sprintf('  <info>%-22s</info> %d 건', $label, $n));
        }
        $this->command?->line(sprintf('  <info>%-22s</info> 서명 %d · 농가사진 %d · 채팅첨부 %d',
            '파일', $this->files['signature'], $this->files['farm_photo'], $this->files['chat_file']));

        $this->command?->newLine();
        $this->command?->line('  <info>[로그인별 화면]</info>');
        $this->command?->line('   NDN 관리자(admin@ndn.test): 대시보드·수요·농가/지자체·후보자·근로자·가입승인·온보딩·정착·월별점검·농가방문·민원·채팅·초대·접속로그');
        $this->command?->line('   농가(farm@ndn.test): 수요 신청(본인 농가)·채팅   /   시청(city@ndn.test): 채팅');
        $this->command?->line('   대리점(partner@ndn.test): 배정 정착(assigned_agency_id=1)·채팅   /   근로자 앱: 온보딩·점검·민원·SOS·채팅');
    }
}
