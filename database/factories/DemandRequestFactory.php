<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Shared\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemandRequest>
 */
class DemandRequestFactory extends Factory
{
    protected $model = DemandRequest::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 month', '+3 months');
        $end = (clone $start)->modify('+5 months');

        return [
            'farm_id' => Farm::factory(),
            'city_id' => null,
            'nationality' => fake()->randomElement(['BD', 'LA', 'LK', 'VN']),
            'headcount' => fake()->numberBetween(1, 20),
            'age_min' => 20,
            'age_max' => 45,
            'gender' => fake()->randomElement(Gender::cases()),
            'allow_siblings' => fake()->boolean(30),
            'crop' => fake()->randomElement(['딸기', '토마토', '오이', '파프리카']),
            'period_start' => $start,
            'period_end' => $end,
            'note' => null,
            'status' => DemandStatus::Draft,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => DemandStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
