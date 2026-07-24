<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Onboarding\Models\ConsentRecord;
use App\Domains\Recruitment\Models\Worker;
use App\Shared\Enums\ConsentPurpose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentRecord>
 */
class ConsentRecordFactory extends Factory
{
    protected $model = ConsentRecord::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'purpose' => fake()->randomElement(ConsentPurpose::cases()),
            'agency_type' => null,
            'agency_id' => null,
            'item' => fake()->randomElement(['passport_no', 'phone', 'bank_account']),
            'granted_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
