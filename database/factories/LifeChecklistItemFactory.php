<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Monitoring\Models\LifeChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LifeChecklistItem>
 */
class LifeChecklistItemFactory extends Factory
{
    protected $model = LifeChecklistItem::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'label' => '확인사항(테스트)',
            'hint' => null,
            'sort_order' => 1,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
