<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Enums\WorkReviewResult;
use App\Domains\Monitoring\Enums\WorkReviewType;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkReview>
 */
class WorkReviewFactory extends Factory
{
    protected $model = WorkReview::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'farm_id' => Farm::factory(),
            'inspector_user_id' => User::factory(),
            'farm_visit_id' => null,
            'reviewed_at' => now(),
            'place' => '농가 현장',
            'review_type' => WorkReviewType::Regular->value,
            'result' => WorkReviewResult::Good->value,
            'wage_unpaid' => false,
            'report_city' => false,
            'report_immigration' => false,
            'risk_score' => 0,
            'risk_level' => RiskLevel::Low->value,
        ];
    }
}
