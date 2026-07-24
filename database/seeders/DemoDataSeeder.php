<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Support\Models\SupportTicket;
use Illuminate\Database\Seeder;

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

        // 근로자 40명
        $workers = Worker::factory()->count(40)->create();

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

        // 온보딩 제출 18건 (검수 단계 분포)
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
            ]);
        });

        // 정착 처리보드 24건 (칸반 5단계 분산)
        $workers->random(24)->each(function ($w) {
            SettlementRequest::factory()->create([
                'worker_id' => $w->id,
                'status' => fake()->randomElement(SettlementStatus::cases()),
            ]);
        });

        // 월별 점검 32건 (대부분 저위험, 일부 고위험)
        $workers->take(32)->each(function ($w) {
            $factory = MonthlyInterview::factory();
            if (fake()->boolean(18)) {
                $factory = $factory->highRisk();
            }
            $factory->create(['worker_id' => $w->id]);
        });

        // 민원 15건
        $workers->random(15)->each(function ($w) {
            SupportTicket::factory()->create(['worker_id' => $w->id]);
        });
    }
}
