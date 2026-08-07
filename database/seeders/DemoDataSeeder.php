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
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 내부 시연·검증용 데모 데이터.
 * 콘솔 각 화면(대시보드/수요/후보자/근로자/온보딩/정착/월별점검/민원)이
 * 비어 보이지 않도록 현실적인 한국 지자체·농가명 기반으로 채운다.
 * 개인정보 필드는 팩토리를 통해 정상 암호화 경로로 저장된다(CLAUDE.md §7).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 지자체 8곳 (팩토리 고정 리스트에서 중복 없이)
        $cities = collect(City::factory()->count(8)->create())->values();

        // 농가 16곳 — 각 지자체에 분산
        $farms = collect(range(1, 16))->map(fn ($i) => Farm::factory()->create([
            'city_id' => $cities->random()->id,
        ]));

        // 근로자 40명 — 지원 지자체를 분산해 지역별 집계 화면에 실제 분포가 생기게 한다
        $workers = collect(range(1, 40))->map(fn ($i) => Worker::factory()->create([
            'city_id' => $cities->random()->id,
        ]));

        // 수요 신청 14건 (대부분 제출/취합 상태로)
        $farms->take(14)->each(function ($farm) use ($cities) {
            DemandRequest::factory()->create([
                'farm_id' => $farm->id,
                'city_id' => $cities->random()->id,
                'status' => fake()->randomElement([
                    DemandStatus::Submitted,
                    DemandStatus::Aggregated,
                    DemandStatus::LetterIssued,
                ]),
                'submitted_at' => now()->subDays(fake()->numberBetween(1, 40)),
            ]);
        });

        // 후보자 28명 (합격/보류/불합격 분포, 보류자는 대기열 번호)
        $queue = 0;
        collect(range(1, 28))->each(function () use (&$queue) {
            $status = fake()->randomElement([
                CandidateStatus::Passed, CandidateStatus::Passed,
                CandidateStatus::Held, CandidateStatus::Rejected,
                CandidateStatus::Applied,
            ]);
            Candidate::factory()->create([
                'status' => $status,
                'queue_position' => $status === CandidateStatus::Held ? ++$queue : null,
            ]);
        });

        // 온보딩 제출 18건 (검수 단계 분포) — 전자서명은 실제 파일로 저장(§9, private)
        $workers->take(18)->each(function ($w) {
            $status = fake()->randomElement([
                OnboardingStatus::Submitted,
                OnboardingStatus::UnderReview,
                OnboardingStatus::Approved,
            ]);
            OnboardingSubmission::factory()->create([
                'worker_id' => $w->id,
                'status' => $status,
                'submitted_at' => now()->subDays(fake()->numberBetween(1, 30)),
                'signature_path' => $this->makeSignature($w->id),
            ]);
        });

        // 정착 처리보드 24건 (칸반 5단계 분산)
        $workers->random(24)->each(function ($w) {
            SettlementRequest::factory()->create([
                'worker_id' => $w->id,
                'status' => fake()->randomElement(SettlementStatus::cases()),
            ]);
        });

        // 근무상태 점검표 32건 (대부분 저위험, 일부 고위험)
        $workers->take(32)->each(fn ($w) => $this->demoReview($w));

        // 생활 체크리스트 — 사람마다 진행 정도가 다르다 (콘솔 정렬을 보려면 필요하다)
        $items = LifeChecklistItem::query()->active()->get();
        if ($items->isNotEmpty()) {
            $workers->take(30)->each(function ($w) use ($items) {
                foreach ($items->random(fake()->numberBetween(0, $items->count())) as $item) {
                    LifeChecklistCheck::create([
                        'worker_id' => $w->id,
                        'life_checklist_item_id' => $item->id,
                        'checked_at' => now()->subDays(fake()->numberBetween(0, 20)),
                    ]);
                }
            });
        }

        // 민원 15건
        $workers->random(15)->each(function ($w) {
            SupportTicket::factory()->create(['worker_id' => $w->id]);
        });
    }

    /**
     * 데모용 근무상태 점검표 1건.
     *
     * 항목별 응답까지 만들지는 않는다 — 화면에서 보는 것은 목록의 등급·점수이고,
     * 43항목을 사람마다 채우면 시드가 느려진다. 실제 응답은 콘솔에서 작성해 본다.
     */
    private function demoReview(Worker $worker): void
    {
        $high = fake()->boolean(18);

        WorkReview::factory()->create([
            'worker_id' => $worker->id,
            'farm_id' => Farm::inRandomOrder()->value('id') ?? Farm::factory(),
            'inspector_user_id' => User::query()->value('id') ?? User::factory(),
            'reviewed_at' => now()->subDays(fake()->numberBetween(1, 60)),
            'result' => $high ? WorkReviewResult::SpecialCare->value : WorkReviewResult::Good->value,
            'risk_score' => $high ? fake()->numberBetween(8, 16) : fake()->numberBetween(0, 2),
            'risk_level' => $high ? RiskLevel::High->value : RiskLevel::Low->value,
        ]);
    }

    /** 데모용 전자서명 PNG 를 private 디스크에 생성하고 경로 반환 */
    private function makeSignature(int $workerId): string
    {
        $img = imagecreatetruecolor(400, 150);
        $white = imagecolorallocate($img, 255, 255, 255);
        $ink = imagecolorallocate($img, 20, 30, 50);
        imagefill($img, 0, 0, $white);
        imagesetthickness($img, 3);
        $px = 40;
        $py = 90;
        for ($x = 40; $x < 360; $x += 6) {
            $y = 90 + (int) (35 * sin($x / 18 + $workerId));
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
}
