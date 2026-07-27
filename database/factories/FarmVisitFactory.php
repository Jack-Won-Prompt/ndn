<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Enums\FarmVisitStatus;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmVisit>
 */
class FarmVisitFactory extends Factory
{
    protected $model = FarmVisit::class;

    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'visited_by' => User::factory(),
            'visited_on' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'farm_status' => FarmVisitStatus::Normal->value,
            'worker_status' => FarmVisitStatus::Normal->value,
            'worker_headcount' => fake()->numberBetween(1, 20),
            'work_note' => fake()->sentence(),
            'issue_note' => null,
            'action_note' => null,
            'memo' => null,
        ];
    }
}
