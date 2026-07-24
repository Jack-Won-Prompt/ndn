<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'demand_request_id' => null,
            'worker_id' => null,
            'name' => fake()->name(),
            'nationality' => fake()->randomElement(['BD', 'LA', 'LK', 'VN']),
            'age' => fake()->numberBetween(20, 45),
            'gender' => fake()->randomElement(['male', 'female']),
            'status' => CandidateStatus::Applied,
            'queue_position' => null,
        ];
    }

    public function held(int $position): static
    {
        return $this->state(fn () => [
            'status' => CandidateStatus::Held,
            'queue_position' => $position,
        ]);
    }
}
