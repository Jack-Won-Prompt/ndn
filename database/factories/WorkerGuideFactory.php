<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Support\Models\WorkerGuide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerGuide>
 */
class WorkerGuideFactory extends Factory
{
    protected $model = WorkerGuide::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'title' => '안내 자료',
            'lead' => '요약 한 줄',
            'icon' => 'school',
            'position' => 1,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
