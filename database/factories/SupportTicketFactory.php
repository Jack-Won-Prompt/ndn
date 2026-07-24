<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Enums\TicketType;
use App\Domains\Support\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'type' => fake()->randomElement(TicketType::cases()),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => TicketStatus::Open,
        ];
    }
}
