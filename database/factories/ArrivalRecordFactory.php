<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Arrival\Enums\ArrivalDocument;
use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Matching\Models\Placement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArrivalRecord>
 */
class ArrivalRecordFactory extends Factory
{
    protected $model = ArrivalRecord::class;

    public function definition(): array
    {
        return [
            'placement_id' => Placement::factory()->confirmed(),
            'status' => ArrivalStatus::Scheduled,
            'flight_no' => 'KE'.fake()->numerify('###'),
            'airport' => '인천(ICN)',
            'scheduled_arrival_at' => now()->addDays(fake()->numberBetween(1, 30)),
            'documents' => ArrivalDocument::emptyChecklist(),
        ];
    }

    /** 필수 서류를 모두 확인한 상태 */
    public function documentsReady(): static
    {
        return $this->state(fn () => [
            'documents' => array_map(
                fn (string $key) => in_array($key, [
                    ArrivalDocument::Passport->value,
                    ArrivalDocument::VisaE8->value,
                    ArrivalDocument::FlightTicket->value,
                ], true),
                array_combine(ArrivalDocument::keys(), ArrivalDocument::keys()),
            ),
        ]);
    }
}
