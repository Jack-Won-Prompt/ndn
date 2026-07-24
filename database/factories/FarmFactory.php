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

    /** 현실적인 한국 농가명 풀 */
    private const NAMES = [
        '햇살농원', '푸른들농장', '금호원예', '대지농원', '한아름농장',
        '초록마을농원', '샛별농장', '드림팜', '해뜨는농원', '청정원예',
        '늘푸른농장', '산들바람농원', '옥토농장', '풍년농원', '토담농장',
        '자연愛농원', '들꽃농장', '새싹농원', '보람농장', '하늘채농원',
    ];

    /** 계절근로자 주요 배치 지역 (읍·면 단위 근사) */
    private const ADDR = [
        '충청남도 당진시 합덕읍', '경상남도 창녕군 대합면', '전라남도 해남군 화원면',
        '강원도 홍천군 서면', '경상북도 영주시 부석면', '충청북도 괴산군 청천면',
        '전라북도 김제시 만경읍', '경기도 이천시 모가면',
    ];

    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'city_id' => City::factory(),
            'name' => fake()->randomElement(self::NAMES),
            'address' => fake()->randomElement(self::ADDR),
            'contact_phone' => fake()->numerify('010-####-####'),
            'main_crop' => fake()->randomElement(['딸기', '토마토', '오이', '파프리카', '사과', '고추', '깻잎', '포도']),
        ];
    }
}
