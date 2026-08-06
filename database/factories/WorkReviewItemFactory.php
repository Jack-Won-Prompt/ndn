<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Monitoring\Enums\WorkReviewSection;
use App\Domains\Monitoring\Models\WorkReviewItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkReviewItem>
 */
class WorkReviewItemFactory extends Factory
{
    protected $model = WorkReviewItem::class;

    public function definition(): array
    {
        return [
            'section' => WorkReviewSection::Attendance->value,
            'code' => fake()->unique()->slug(2),
            'label' => '점검 항목(테스트)',
            'adverse' => false,
            'scored' => true,
            'sort_order' => 1,
            'active' => true,
        ];
    }

    public function section(WorkReviewSection $section): static
    {
        return $this->state(fn () => ['section' => $section->value]);
    }

    /** 확인된 쪽이 문제인 항목 (임금 체불 여부 등) */
    public function adverse(): static
    {
        return $this->state(fn () => ['section' => WorkReviewSection::Safety->value, 'adverse' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
