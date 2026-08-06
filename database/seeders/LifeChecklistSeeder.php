<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Monitoring\Models\LifeChecklistItem;
use Illuminate\Database\Seeder;

/**
 * 한국 생활 체크리스트 항목 — 입국 후 1주일 이내 확인사항 12가지.
 *
 * 원본: storage/app/worker-documents/life-checklist.docx 부록1.
 * 사전교육 통합본 §6 에도 같은 목록이 짧게 실려 있으나, 부록1 이 더 자세해
 * 그쪽을 따랐다. 두 곳에 각각 적지 않도록 사전교육 안내 자료에서는 뺐다.
 *
 * 문구는 콘솔에서 고칠 수 있게 DB 에 둔다. code 는 바꾸지 말 것 —
 * 기존 체크 기록이 코드가 아니라 행 id 로 붙어 있어 항목을 지웠다 다시 만들면
 * 근로자가 확인한 이력이 끊긴다.
 */
class LifeChecklistSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $i => $item) {
            LifeChecklistItem::updateOrCreate(
                ['code' => $item['code']],
                [
                    'label' => $item['label'],
                    'hint' => $item['hint'] ?? null,
                    'sort_order' => $i + 1,
                    // active 는 건드리지 않는다 — 운영에서 내려 둔 항목이 다시 켜지면 안 된다.
                ],
            );
        }
    }

    /** @return list<array{code: string, label: string, hint?: string}> */
    private function items(): array
    {
        return [
            [
                'code' => 'documents_location',
                'label' => '여권 및 외국인등록증(또는 신청서류) 보관 위치 확인',
                'hint' => '본인이 직접 보관합니다. 누구에게도 맡기지 마십시오.',
            ],
            [
                'code' => 'contract_reviewed',
                'label' => '근로계약서 내용 확인',
                'hint' => '근무시간·임금·연장근무 기준이 적혀 있습니다.',
            ],
            [
                'code' => 'dorm_address',
                'label' => '숙소 주소 및 연락처 확인',
                'hint' => '길을 잃거나 신고할 때 주소를 말할 수 있어야 합니다.',
            ],
            [
                'code' => 'farm_owner_contact',
                'label' => '농장주(고용주) 연락처 저장',
            ],
            [
                'code' => 'ndn_contact',
                'label' => 'NDN KOREA 담당자 연락처 저장',
            ],
            [
                'code' => 'emergency_numbers',
                'label' => '긴급전화(112·119) 저장',
                'hint' => '외국인종합안내센터 1345 도 함께 저장하십시오.',
            ],
            [
                'code' => 'utility_usage',
                'label' => '숙소 내 전기·가스 사용방법 확인',
            ],
            [
                'code' => 'appliance_usage',
                'label' => '세탁기·전자레인지·냉장고 사용방법 확인',
                'hint' => '부주의로 파손하면 비용을 부담할 수 있습니다.',
            ],
            [
                'code' => 'waste_separation',
                'label' => '쓰레기 분리배출 방법 확인',
            ],
            [
                'code' => 'work_schedule',
                'label' => '출근시간 및 집합장소 확인',
            ],
            [
                'code' => 'living_costs',
                'label' => '월 생활비 및 공과금 확인',
                'hint' => '예상 금액은 사전교육 안내에서 볼 수 있습니다.',
            ],
            [
                'code' => 'nearby_facilities',
                'label' => '병원·약국·편의점 위치 확인',
            ],
        ];
    }
}
