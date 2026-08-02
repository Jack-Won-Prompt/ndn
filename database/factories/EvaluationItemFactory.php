<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Recruitment\Models\EvaluationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationItem>
 */
class EvaluationItemFactory extends Factory
{
    protected $model = EvaluationItem::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->lexify('item_????'),
            'label' => '평가 항목',
            'hint' => null,
            'max_score' => 20,
            'sort_order' => 1,
            'active' => true,
        ];
    }
}
