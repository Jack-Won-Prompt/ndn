<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyInterview>
 */
class MonthlyInterviewFactory extends Factory
{
    protected $model = MonthlyInterview::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'inspector_user_id' => null,
            'interviewed_on' => now()->toDateString(),
            'pay_received' => true,
            'no_discrimination' => true,
            'follows_rules' => true,
            'adapts_group' => true,
            'health_ok' => true,
            'no_flight_signs' => true,
            'risk_score' => 0,
            'risk_level' => RiskLevel::Low,
            'memo' => null,
        ];
    }

    /** 고위험 상태 (3개 부정) */
    public function highRisk(): static
    {
        return $this->state(fn () => [
            'pay_received' => false,
            'health_ok' => false,
            'no_flight_signs' => false,
            'risk_score' => 3,
            'risk_level' => RiskLevel::High,
        ]);
    }
}
