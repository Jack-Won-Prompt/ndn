<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Support\Enums\ServiceRequestStatus;
use App\Domains\Support\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return [
            'requester_user_id' => User::factory(),
            'title' => fake()->randomElement([
                '근로자 목록에 지역별 필터가 필요합니다',
                '월별 점검 저장 시 오류가 납니다',
                '정착 처리보드에서 엑셀 내려받기 추가 요청',
            ]),
            'body' => fake()->realText(120),
            'status' => ServiceRequestStatus::Received,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => ServiceRequestStatus::InProgress,
            'assignee_user_id' => User::factory(),
        ]);
    }
}
