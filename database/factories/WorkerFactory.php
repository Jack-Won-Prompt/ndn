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

    /** 국적별 현실적인 이름 풀 (송출 4개국) */
    public const NAMES = [
        'BD' => ['Md. Rahman', 'Abdul Karim', 'Mohammad Hasan', 'Shakil Ahmed', 'Jamal Uddin', 'Rafiqul Islam', 'Nazrul Haque', 'Kamal Hossain'],
        'LA' => ['Somchai Vong', 'Bounmy Phet', 'Khamla Sisouk', 'Thongchan Keo', 'Vilay Sengdao', 'Somsak Chanthavong'],
        'LK' => ['Nuwan Perera', 'Kasun Silva', 'Chaminda Fernando', 'Sunil Jayawardena', 'Dinesh Bandara', 'Ruwan Gunawardena'],
        'VN' => ['Nguyen Van An', 'Tran Van Binh', 'Le Van Cuong', 'Pham Van Dung', 'Hoang Van Em', 'Vu Van Phong'],
    ];

    /** 국적 → locale 매핑 */
    private const LOCALE = ['BD' => 'bn', 'LA' => 'lo', 'LK' => 'si', 'VN' => 'vi', 'NP' => 'ne', 'KG' => 'ky'];

    /** 국적 → 여권번호 접두 (국가별 형식 근사) */
    private const PASSPORT_PREFIX = ['BD' => 'BW', 'LA' => 'P', 'LK' => 'N', 'VN' => 'C'];

    public function definition(): array
    {
        $nat = fake()->randomElement(['BD', 'LA', 'LK', 'VN']);

        return [
            'name' => fake()->randomElement(self::NAMES[$nat]),
            // 앱 로그인 자격증명 (§9). password 는 hashed cast 로 저장 시 해시된다.
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'nationality' => $nat,
            'locale' => self::LOCALE[$nat],
            'status' => 'active',
            'passport_no' => self::PASSPORT_PREFIX[$nat].fake()->numerify('#######'),
            'birth_date' => fake()->date('Y-m-d', '2004-01-01'),
            'phone_home_country' => fake()->numerify('+8801#########'),
        ];
    }
}
