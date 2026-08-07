<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Enums\WorkReviewResult;
use App\Domains\Monitoring\Models\LifeChecklistCheck;
use App\Domains\Monitoring\Models\LifeChecklistItem;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Support\Models\ChatMessage;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\ChatService;
use App\Models\User;
use App\Shared\Translation\GoogleTranslator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 정합성 검증 테스트 데이터 20건(근로자 기준) + 파일(전자서명).
 * 운영에서도 명시 실행:
 *   php artisan db:seed --class=Database\\Seeders\\IntegrityTestSeeder --force
 *
 * 근로자 20명을 축으로 온보딩(서명파일)·근무상태점검·생활체크리스트·민원·정착·수요·후보자·채팅(자동번역)을
 * 참조무결성 있게 생성하고, 마지막에 정합성 검증 결과를 출력한다.
 */
class IntegrityTestSeeder extends Seeder
{
    private const N = 20;

    public function run(): void
    {
        $tag = 'SEED20';   // 이번 배치 식별용 표식

        // ── 기준정보 확보 ──
        $cities = City::count() >= 5 ? City::inRandomOrder()->take(8)->get() : City::factory()->count(8)->create();
        $farms = Farm::count() >= 8
            ? Farm::inRandomOrder()->take(10)->get()
            : Farm::factory()->count(10)->create(['city_id' => fn () => $cities->random()->id]);

        $this->command?->info('기준정보: 지자체 '.$cities->count().', 농가 '.$farms->count());

        // ── 근로자 20명 + 관련 데이터 ──
        $svc = app(ChatService::class);
        $workers = collect();
        $sigCount = 0;

        for ($i = 0; $i < self::N; $i++) {
            $w = Worker::factory()->create(['city_id' => $cities->random()->id]);
            $workers->push($w);

            // 온보딩 + 실제 전자서명 파일(§9 private)
            OnboardingSubmission::factory()->submitted()->create([
                'worker_id' => $w->id,
                'status' => fake()->randomElement([
                    OnboardingStatus::Submitted, OnboardingStatus::UnderReview, OnboardingStatus::Approved,
                ]),
                'payload' => [
                    'address_kr' => fake()->randomElement($cities->pluck('region')->all()).' '.fake()->buildingNumber().'번지',
                    'emergency_contact' => fake('ko_KR')->name().' / '.fake()->numerify('010-####-####'),
                ],
                'signature_path' => $this->makeSignature($w->id),
            ]);
            $sigCount++;

            // 근무상태 점검표 (대부분 저위험, 일부 고위험)
            $high = fake()->boolean(20);
            WorkReview::factory()->create([
                'worker_id' => $w->id,
                'farm_id' => $farms->random()->id,
                'inspector_user_id' => User::query()->value('id') ?? User::factory(),
                'reviewed_at' => now()->subDays(fake()->numberBetween(1, 60)),
                'result' => $high ? WorkReviewResult::SpecialCare->value : WorkReviewResult::Good->value,
                'risk_score' => $high ? fake()->numberBetween(8, 16) : fake()->numberBetween(0, 2),
                'risk_level' => $high ? RiskLevel::High->value : RiskLevel::Low->value,
            ]);

            // 생활 체크리스트 — 사람마다 진행 정도가 다르다
            $checklist = LifeChecklistItem::query()->active()->get();
            foreach ($checklist->random(fake()->numberBetween(0, $checklist->count())) as $item) {
                LifeChecklistCheck::create([
                    'worker_id' => $w->id,
                    'life_checklist_item_id' => $item->id,
                    'checked_at' => now()->subDays(fake()->numberBetween(0, 20)),
                ]);
            }

            // 절반 민원 / 절반 정착
            if (fake()->boolean(50)) {
                SupportTicket::factory()->create(['worker_id' => $w->id]);
            }
            if (fake()->boolean(50)) {
                SettlementRequest::factory()->create(['worker_id' => $w->id]);
            }
        }

        // ── 수요 신청 10건 ──
        $farms->take(10)->each(function (Farm $farm) use ($cities) {
            DemandRequest::factory()->create([
                'farm_id' => $farm->id,
                'city_id' => $cities->random()->id,
                'status' => fake()->randomElement([
                    DemandStatus::Submitted, DemandStatus::Aggregated, DemandStatus::LetterIssued,
                ]),
                'submitted_at' => now()->subDays(fake()->numberBetween(1, 40)),
            ]);
        });

        // ── 후보자 10명 ──
        collect(range(1, 10))->each(function () {
            $status = fake()->randomElement([
                CandidateStatus::Applied, CandidateStatus::Passed, CandidateStatus::Held, CandidateStatus::Rejected,
            ]);
            Candidate::factory()->create(['status' => $status]);
        });

        // ── 채팅: NDN ↔ 근로자 (앞 5명, 자동 번역) ──
        $chatCount = 0;
        foreach ($workers->take(5) as $w) {
            try {
                $wp = $svc->partyForWorker($w);
                $conv = $svc->resolveConversation(['ndn', null, 'ko'], $wp);
                // NDN 은 한국어로, 근로자는 자국어로 작성(자국어 문장은 의도를 자국어로 번역해 생성)
                $svc->send($conv, ['ndn', null, 'ko'], '안녕하세요. 온보딩 서류가 확인되었습니다. 배정 일정을 안내드리겠습니다.');
                $workerBody = GoogleTranslator::translate('감사합니다. 언제 입국하나요?', $wp[2], 'ko');
                $svc->send($conv, $wp, $workerBody);
                $chatCount++;
            } catch (\Throwable $e) {
                $this->command?->warn('채팅 생성 일부 실패: '.$e->getMessage());
            }
        }

        $this->command?->info("근로자 {$workers->count()}명 · 서명파일 {$sigCount} · 채팅 {$chatCount}건 생성");

        $this->verify($workers, $tag);
    }

    /** 데모용 전자서명 PNG 를 private 디스크에 생성하고 경로 반환 */
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
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        $path = 'onboarding/signatures/'.$workerId.'_'.Str::uuid()->toString().'.png';
        Storage::disk('local')->put($path, $png);

        return $path;
    }

    /** 정합성 검증 후 결과 출력 */
    private function verify($workers, string $tag): void
    {
        $ids = $workers->pluck('id');
        $pass = 0;
        $fail = 0;
        $log = function (string $label, bool $ok) use (&$pass, &$fail) {
            $this->command?->line(($ok ? '  <info>[OK]</info> ' : '  <error>[!!]</error> ').$label);
            $ok ? $pass++ : $fail++;
        };

        // 1) 서명 파일 실제 존재
        $subs = OnboardingSubmission::whereIn('worker_id', $ids)->get();
        $filesOk = $subs->every(fn ($s) => filled($s->signature_path) && Storage::disk('local')->exists($s->signature_path));
        $log('전자서명 파일 전부 디스크 존재('.$subs->count().'건)', $filesOk);

        // 2) 암호화(여권) + blind index
        $w = $workers->first()->fresh();
        $raw = DB::table('workers')->where('id', $w->id)->first();
        $log('여권 복호화 정상', filled($w->passport_no));
        $log('여권 원문 암호화(평문 아님)', str_starts_with((string) $raw->passport_no, 'eyJ'));
        $log('blind index 설정됨', ! empty($raw->passport_no_bidx));

        // 3) 참조무결성
        $log('온보딩 worker_id 실존', OnboardingSubmission::whereIn('worker_id', $ids)->whereNotIn('worker_id', Worker::pluck('id'))->count() === 0);
        $log('demand.farm_id 실존', DemandRequest::whereNotNull('farm_id')->whereNotIn('farm_id', Farm::pluck('id'))->count() === 0);
        $log('demand.city_id 실존', DemandRequest::whereNotNull('city_id')->whereNotIn('city_id', City::pluck('id'))->count() === 0);

        // 4) Enum 유효성
        $log('demand.status 유효', DemandRequest::whereNotIn('status', ['draft', 'submitted', 'aggregated', 'letter_issued', 'rejected'])->count() === 0);
        $log('candidate.status 유효', Candidate::whereNotIn('status', ['applied', 'passed', 'held', 'rejected'])->count() === 0);
        $log('worker 국적 코드 유효', Worker::whereIn('id', $ids)->whereNotIn('nationality', ['BD', 'LA', 'LK', 'VN'])->count() === 0);

        // 5) 채팅 정합성(번역본 언어 = 상대 언어)
        $badTrans = ChatMessage::whereNotNull('translated_body')
            ->where('translate_lang', '')->count();
        $log('채팅 번역 언어 태그 정상', $badTrans === 0);

        $this->command?->newLine();
        if ($fail === 0) {
            $this->command?->info("정합성 검증 통과: {$pass}/{$pass}  (근로자 20명 + 파일 + 관련데이터)");
        } else {
            $this->command?->error("정합성 검증: PASS={$pass} FAIL={$fail}");
        }
    }
}
