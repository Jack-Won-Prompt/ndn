<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Placement>
 */
class PlacementFactory extends Factory
{
    protected $model = Placement::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'farm_id' => Farm::factory(),
            'status' => PlacementStatus::Proposed,
            'start_date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addMonths(5)->toDateString(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => PlacementStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
