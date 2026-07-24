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

    /** 계절근로자 프로그램 참여 지자체 (실제 지역명) */
    private const CITIES = [
        ['당진시', '충청남도'], ['창녕군', '경상남도'], ['해남군', '전라남도'],
        ['홍천군', '강원도'], ['영주시', '경상북도'], ['괴산군', '충청북도'],
        ['김제시', '전라북도'], ['이천시', '경기도'], ['무주군', '전라북도'],
        ['봉화군', '경상북도'],
    ];

    public function definition(): array
    {
        [$name, $region] = fake()->randomElement(self::CITIES);

        return [
            'name' => $name,
            'region' => $region,
        ];
    }
}
