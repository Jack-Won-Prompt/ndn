<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    protected $model = Farm::class;

    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'city_id' => City::factory(),
            'name' => fake()->lastName().' 농원',
            'address' => fake()->address(),
            'contact_phone' => fake()->numerify('010-####-####'),
            'main_crop' => fake()->randomElement(['딸기', '토마토', '오이', '파프리카', '사과']),
        ];
    }
}
