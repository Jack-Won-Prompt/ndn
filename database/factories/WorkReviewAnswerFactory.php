<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Monitoring\Models\WorkReviewAnswer;
use App\Domains\Monitoring\Models\WorkReviewItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkReviewAnswer>
 */
class WorkReviewAnswerFactory extends Factory
{
    protected $model = WorkReviewAnswer::class;

    public function definition(): array
    {
        return [
            'work_review_id' => WorkReview::factory(),
            'work_review_item_id' => WorkReviewItem::factory(),
            'value' => 'high',
            'note' => null,
        ];
    }
}
