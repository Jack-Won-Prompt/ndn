<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingSubmission>
 */
class OnboardingSubmissionFactory extends Factory
{
    protected $model = OnboardingSubmission::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'payload' => [
                'address_kr' => fake()->address(),
                'emergency_contact' => fake()->name().' / '.fake()->phoneNumber(),
            ],
            'signature_path' => 'onboarding/signatures/'.fake()->uuid().'.png',
            'status' => OnboardingStatus::Draft,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => OnboardingStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
