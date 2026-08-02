<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Onboarding\Models\RequiredDocument;
use Illuminate\Database\Seeder;

/**
 * 근로자 필수 확인·동의 문서 5종의 **틀**만 만든다.
 *
 * 본문(약관 전문)은 법적 효력이 있는 문안이라 코드에 넣지 않는다.
 * 관리자가 콘솔 [필수 동의 문서] 화면에서 언어별로 입력한다.
 * 이미 있는 문서는 건드리지 않는다 — 입력해 둔 본문을 덮어쓰면 안 된다.
 */
class RequiredDocumentSeeder extends Seeder
{
    /** code => [한국어 제목, 정렬 순서] */
    private const DOCUMENTS = [
        'worker_duties' => ['근로자 의무사항', 1],
        'standard_contract' => ['표준근로계약서', 2],
        'insurance_agreement' => ['상해보험 가입의무 약정서', 3],
        'retention_agreement' => ['이탈방지 표준근로계약서', 4],
        'deposit_service_agreement' => ['보증금 및 서비스지원 약정서', 5],
    ];

    public function run(): void
    {
        foreach (self::DOCUMENTS as $code => [$title, $order]) {
            RequiredDocument::firstOrCreate(
                ['code' => $code],
                [
                    // 본문은 비워 둔다 — 콘솔에서 입력할 때까지 동의 화면에 제목만 보인다.
                    'translations' => ['ko' => ['title' => $title, 'body' => '']],
                    'version' => 1,
                    'sort_order' => $order,
                    'required' => true,
                    // 본문이 비어 있는 상태로 근로자에게 노출되면 안 되므로 꺼 둔 채 만든다.
                    // 콘솔에서 본문을 채운 뒤 '사용'으로 켠다.
                    'active' => false,
                ],
            );
        }
    }
}
