<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Monitoring\Enums\WorkReviewSection;
use App\Domains\Monitoring\Models\WorkReviewItem;
use Illuminate\Database\Seeder;

/**
 * 근무상태 종합 점검표 항목 43가지.
 *
 * 원본: storage/app/worker-documents/work-status-review.docx §4~§7.
 * 원본 §1·§8·§10·§11·§12 는 항목이 아니라 점검표 본문 필드라 work_reviews 컬럼이다.
 *
 * code 는 바꾸지 말 것 — 지난 응답이 코드가 아니라 행 id 로 붙어 있어 항목을
 * 지웠다 다시 만들면 기록이 끊긴다. 문구는 콘솔에서 고칠 수 있다.
 */
class WorkReviewItemSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;

        foreach ($this->items() as $item) {
            $order++;
            WorkReviewItem::updateOrCreate(
                ['code' => $item['code']],
                [
                    'section' => $item['section'],
                    'label' => $item['label'],
                    'adverse' => $item['adverse'] ?? false,
                    'scored' => $item['scored'] ?? true,
                    'sort_order' => $order,
                    // active 는 건드리지 않는다 — 운영에서 내려 둔 항목이 다시 켜지면 안 된다.
                ],
            );
        }
    }

    /** @return list<array{section: string, code: string, label: string, adverse?: bool, scored?: bool}> */
    private function items(): array
    {
        $attendance = WorkReviewSection::Attendance->value;
        $performance = WorkReviewSection::Performance->value;
        $community = WorkReviewSection::Community->value;
        $safety = WorkReviewSection::Safety->value;

        return [
            // §4 근태 및 출·퇴근 — 양호 / 보통 / 미흡
            ['section' => $attendance, 'code' => 'attendance_arrival', 'label' => '출근시간 준수'],
            ['section' => $attendance, 'code' => 'attendance_departure', 'label' => '퇴근시간 준수'],
            ['section' => $attendance, 'code' => 'attendance_late', 'label' => '지각 여부'],
            ['section' => $attendance, 'code' => 'attendance_absence', 'label' => '결근 여부'],
            ['section' => $attendance, 'code' => 'attendance_awol', 'label' => '무단이탈 여부'],
            ['section' => $attendance, 'code' => 'attendance_break', 'label' => '휴게시간 준수'],
            ['section' => $attendance, 'code' => 'attendance_overtime', 'label' => '연장근무 실시 여부'],
            ['section' => $attendance, 'code' => 'attendance_record', 'label' => '근태기록부 작성'],

            // §5 근무상태 및 업무능력 — 우수 / 양호 / 개선 필요
            ['section' => $performance, 'code' => 'perf_understanding', 'label' => '작업 이해도'],
            ['section' => $performance, 'code' => 'perf_speed', 'label' => '작업 속도'],
            ['section' => $performance, 'code' => 'perf_accuracy', 'label' => '작업 정확도'],
            ['section' => $performance, 'code' => 'perf_responsibility', 'label' => '책임감'],
            ['section' => $performance, 'code' => 'perf_diligence', 'label' => '성실성'],
            ['section' => $performance, 'code' => 'perf_instructions', 'label' => '지시사항 이행'],
            ['section' => $performance, 'code' => 'perf_focus', 'label' => '집중력'],
            ['section' => $performance, 'code' => 'perf_adaptation', 'label' => '업무 적응도'],
            ['section' => $performance, 'code' => 'perf_stamina', 'label' => '체력 및 지속 작업능력'],
            ['section' => $performance, 'code' => 'perf_harvest', 'label' => '수확작업 능력'],
            ['section' => $performance, 'code' => 'perf_sorting', 'label' => '선별·포장 능력'],
            ['section' => $performance, 'code' => 'perf_machinery', 'label' => '농기계 사용 능력'],
            ['section' => $performance, 'code' => 'perf_korean', 'label' => '한국어 의사소통'],

            // §6 협동 및 생활관리 — 우수 / 양호 / 미흡
            ['section' => $community, 'code' => 'comm_teamwork', 'label' => '동료와의 협동능력'],
            ['section' => $community, 'code' => 'comm_farm_relation', 'label' => '농가와의 관계'],
            ['section' => $community, 'code' => 'comm_group_living', 'label' => '공동생활 준수'],
            ['section' => $community, 'code' => 'comm_dorm_clean', 'label' => '숙소 청결상태'],
            ['section' => $community, 'code' => 'comm_hygiene', 'label' => '개인 위생상태'],
            ['section' => $community, 'code' => 'comm_alcohol', 'label' => '음주 관련 문제'],
            ['section' => $community, 'code' => 'comm_smoking', 'label' => '흡연 관련 문제'],
            ['section' => $community, 'code' => 'comm_conflict', 'label' => '갈등 발생 여부'],
            ['section' => $community, 'code' => 'comm_unauthorized_outing', 'label' => '무단 외출 여부'],
            // 이탈 판단의 핵심 항목 — 미흡이면 점수와 무관하게 고위험이 된다.
            ['section' => $community, 'code' => WorkReviewItem::FLIGHT_RISK, 'label' => '이탈 가능성 여부'],

            // §7 안전·보건 및 법정사항 — 확인 / 미확인
            ['section' => $safety, 'code' => 'safety_training', 'label' => '안전교육 실시 여부'],
            ['section' => $safety, 'code' => 'safety_ppe_provided', 'label' => '보호구(장갑·장화) 지급 여부'],
            ['section' => $safety, 'code' => 'safety_ppe_worn', 'label' => '보호구 착용 여부'],
            ['section' => $safety, 'code' => 'safety_emergency_contacts', 'label' => '응급연락처 숙지 여부'],
            ['section' => $safety, 'code' => 'safety_emergency_numbers', 'label' => '112·119 인지 여부'],
            ['section' => $safety, 'code' => 'safety_insurance', 'label' => '산업재해보험 가입 여부'],
            // 확인된 쪽이 문제인 항목들 — 방향을 뒤집지 않으면 리스크가 정반대로 매겨진다.
            ['section' => $safety, 'code' => 'safety_health_issue', 'label' => '건강 이상 여부', 'adverse' => true],
            // 진료를 받은 것 자체는 좋고 나쁨이 아니다. 기록만 남긴다.
            ['section' => $safety, 'code' => 'safety_recent_hospital', 'label' => '최근 병원 진료 여부', 'scored' => false],
            ['section' => $safety, 'code' => 'safety_heat_cold_training', 'label' => '폭염·한파 교육 여부'],
            ['section' => $safety, 'code' => 'safety_contract_copy', 'label' => '근로계약서 교부 여부'],
            ['section' => $safety, 'code' => 'safety_payslip', 'label' => '임금명세서 수령 여부'],
            ['section' => $safety, 'code' => 'safety_wage_arrears', 'label' => '임금 체불 여부', 'adverse' => true],
        ];
    }
}
