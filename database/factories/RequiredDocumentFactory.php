<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Onboarding\Models\RequiredDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequiredDocument>
 */
class RequiredDocumentFactory extends Factory
{
    protected $model = RequiredDocument::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'translations' => [
                'ko' => ['title' => '근로자 의무사항', 'body' => '본문(테스트)'],
                'vi' => ['title' => 'Nghĩa vụ của người lao động', 'body' => 'Nội dung (kiểm thử)'],
            ],
            'version' => 1,
            'sort_order' => 1,
            'required' => true,
            'active' => true,
        ];
    }

    /** 열람만 하고 동의는 받지 않는 문서 */
    public function optional(): static
    {
        return $this->state(fn () => ['required' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
