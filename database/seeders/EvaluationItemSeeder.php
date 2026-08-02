<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Recruitment\Models\EvaluationItem;
use Illuminate\Database\Seeder;

/**
 * 면접 평가 체크리스트 초안 (E-8 계절근로자 현지 면접 기준, 합계 100점).
 *
 * 어디까지나 출발점이다 — 항목·배점·문구는 콘솔 [후보자·평가 > 평가 항목]에서
 * 자유롭게 고치고 추가·삭제할 수 있다. 이미 있는 key 는 덮어쓰지 않는다.
 */
class EvaluationItemSeeder extends Seeder
{
    /** key => [표시명, 판단 기준, 배점, 순서] */
    private const ITEMS = [
        'health' => ['건강 상태', '농작업을 감당할 체력·병력·복용 약물 여부', 20, 1],
        'experience' => ['농작업 경험', '재배 품목 경험, 농기계·시설 취급 경험', 20, 2],
        'diligence' => ['성실성·근로 의욕', '결근·이직 이력, 근로 동기, 면접 태도', 20, 3],
        'communication' => ['의사소통', '기초 한국어, 지시 이해도, 통역 없이 소통 가능한 정도', 15, 4],
        'family_ties' => ['가족·연고 안정성', '본국 부양가족·귀국 사유 — 이탈 위험 판단', 15, 5],
        'contract_understanding' => ['계약 이해·준수 의지', '근로조건·체류기간·이탈 시 불이익 이해 여부', 10, 6],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $key => [$label, $hint, $max, $order]) {
            EvaluationItem::firstOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'hint' => $hint,
                    'max_score' => $max,
                    'sort_order' => $order,
                    'active' => true,
                ],
            );
        }
    }
}
