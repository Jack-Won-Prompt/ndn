<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Recruitment\Models\Worker;
use App\Shared\Notifications\Models\DeviceToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        return [
            // 기본은 근로자 기기. 담당자 앱은 state 로 바꾼다.
            'tokenable_type' => (new Worker)->getMorphClass(),
            'tokenable_id' => Worker::factory(),
            // FCM 등록 토큰은 140자 안팎의 긴 문자열이다.
            'token' => fake()->regexify('[A-Za-z0-9_:-]{152}'),
            'platform' => 'android',
            'locale' => 'ko',
            'app_version' => '1.0.0',
            'last_used_at' => now(),
        ];
    }
}
