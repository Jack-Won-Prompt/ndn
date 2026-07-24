<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Demand\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        return [
            'name' => fake()->city().'시',
            'region' => fake()->randomElement(['충청남도', '경상남도', '전라남도', '강원도']),
        ];
    }
}
