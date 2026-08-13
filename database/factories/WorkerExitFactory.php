<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\WorkerExitReason;
use App\Domains\Support\Enums\WorkerExitStatus;
use App\Domains\Support\Enums\WorkerExitType;
use App\Domains\Support\Models\WorkerExit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerExit>
 */
class WorkerExitFactory extends Factory
{
    protected $model = WorkerExit::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'placement_id' => null,
            'support_ticket_id' => null,
            'type' => WorkerExitType::EarlyReturn->value,
            'status' => WorkerExitStatus::Requested->value,
            'reason' => WorkerExitReason::Personal->value,
            'reason_detail' => null,
            'occurred_on' => now()->subDays(3)->toDateString(),
            'noticed_on' => null,
            'note' => null,
        ];
    }

    /** 이탈·연락두절 건 — 시작 상태가 다르다. */
    public function absconded(): static
    {
        return $this->state(fn () => [
            'type' => WorkerExitType::Absconded->value,
            'status' => WorkerExitStatus::Unreachable->value,
            'reason' => WorkerExitReason::Unknown->value,
            'noticed_on' => now()->subDays(1)->toDateString(),
        ]);
    }
}
