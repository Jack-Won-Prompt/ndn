<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Enums\SettlementType;
use App\Domains\Settlement\Models\SettlementRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettlementRequest>
 */
class SettlementRequestFactory extends Factory
{
    protected $model = SettlementRequest::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'type' => fake()->randomElement(SettlementType::cases()),
            'assigned_agency_id' => null,
            'status' => SettlementStatus::Received,
        ];
    }

    public function assignedTo(int $agencyId): static
    {
        return $this->state(fn () => ['assigned_agency_id' => $agencyId]);
    }
}
