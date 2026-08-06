<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Support\Models\WorkerGuide;
use App\Domains\Support\Models\WorkerGuideSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerGuideSection>
 */
class WorkerGuideSectionFactory extends Factory
{
    protected $model = WorkerGuideSection::class;

    public function definition(): array
    {
        return [
            'worker_guide_id' => WorkerGuide::factory(),
            'type' => 'text',
            'position' => 1,
            'payload' => ['heading' => '머리말', 'body' => '본문(테스트)'],
        ];
    }
}
