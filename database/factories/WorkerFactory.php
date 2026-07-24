<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Recruitment\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worker>
 */
class WorkerFactory extends Factory
{
    protected $model = Worker::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'nationality' => fake()->randomElement(['BD', 'LA', 'LK', 'VN']),
            'locale' => fake()->randomElement(['bn', 'lo', 'si', 'vi']),
            'status' => 'active',
            // 여권번호 형식: 대문자 1 + 숫자 7~8 (blind index 검색 테스트에 사용)
            'passport_no' => fake()->bothify('?#######'),
            'birth_date' => fake()->date('Y-m-d', '2004-01-01'),
            'phone_home_country' => fake()->numerify('+8801#########'),
        ];
    }
}
