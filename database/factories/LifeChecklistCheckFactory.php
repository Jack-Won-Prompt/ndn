<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Monitoring\Models\LifeChecklistCheck;
use App\Domains\Monitoring\Models\LifeChecklistItem;
use App\Domains\Recruitment\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LifeChecklistCheck>
 */
class LifeChecklistCheckFactory extends Factory
{
    protected $model = LifeChecklistCheck::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'life_checklist_item_id' => LifeChecklistItem::factory(),
            'checked_at' => now(),
        ];
    }
}
