<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Enums\TicketType;
use App\Domains\Support\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    /** 유형별 현실적인 한국어 민원 내용 */
    private const CONTENT = [
        'report' => [
            ['숙소 온수가 나오지 않습니다', '3일째 온수가 안 나와 찬물로 씻고 있습니다. 확인 부탁드립니다.'],
            ['작업 중 손을 다쳤습니다', '어제 수확 작업 중 낫에 손가락을 베였습니다. 병원 안내가 필요합니다.'],
            ['급여가 계약과 다릅니다', '이번 달 급여가 근로계약서 금액보다 적게 들어왔습니다.'],
            ['숙소 난방이 되지 않습니다', '밤에 너무 추워 잠을 못 잡니다. 난방 점검을 요청합니다.'],
        ],
        'inquiry' => [
            ['통장 개설은 어떻게 하나요', '급여를 받을 계좌를 만들고 싶은데 필요한 서류가 무엇인지 궁금합니다.'],
            ['건강보험 적용 범위 문의', '병원 진료 시 보험이 어디까지 적용되는지 알고 싶습니다.'],
            ['휴대폰 유심 재발급 문의', '유심을 분실했는데 어떻게 재발급받는지 문의드립니다.'],
            ['휴일 근무 규정 문의', '공휴일에 일할 경우 수당이 어떻게 되는지 궁금합니다.'],
        ],
        'extend_stay' => [
            ['체류 기간 연장을 신청합니다', '농장주와 협의하여 다음 시즌까지 더 일하고 싶습니다.'],
            ['계약 연장 절차 문의', '현재 계약이 곧 끝나는데 연장이 가능한지 신청합니다.'],
        ],
        'early_return' => [
            ['조기 귀국을 신청합니다', '가족의 건강 문제로 예정보다 일찍 귀국해야 합니다.'],
            ['본국 사정으로 조기 귀국 요청', '개인 사정으로 이번 달 말 귀국을 희망합니다.'],
        ],
    ];

    public function definition(): array
    {
        $type = fake()->randomElement(TicketType::cases());
        [$subject, $body] = fake()->randomElement(self::CONTENT[$type->value]);

        return [
            'worker_id' => Worker::factory(),
            'type' => $type,
            'subject' => $subject,
            'body' => $body,
            'status' => TicketStatus::Open,
        ];
    }
}
