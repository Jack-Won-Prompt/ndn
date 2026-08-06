<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Onboarding\Models\RequiredDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 필수 확인·동의 문서 5종의 **틀**만 만든다.
 *
 * 본문(약관 전문)은 법적 효력이 있는 문안이라 코드에 넣지 않는다.
 * 관리자가 콘솔 [필수 동의 문서] 화면에서 언어별로 입력한다.
 * 이미 있는 문서는 건드리지 않는다 — 입력해 둔 본문을 덮어쓰면 안 된다.
 */
class RequiredDocumentSeeder extends Seeder
{
    /**
     * code => [한국어 제목, 정렬 순서, 원본 파일명|null]
     *
     * 원본이 붙은 문서는 화면에 본문을 옮겨 적지 않고 파일을 받아 읽게 한다.
     * 법적 서식을 손으로 옮기면 원본과 달라질 수 있다.
     */
    private const DOCUMENTS = [
        'work_consent' => ['계절근로자(E-8-1) 근로 동의서', 1, 'work-consent.pdf'],
        'worker_duties' => ['근로자 의무사항', 2, null],
        'standard_contract' => ['표준근로계약서', 3, null],
        'insurance_agreement' => ['상해보험 가입의무 약정서', 4, null],
        'retention_agreement' => ['이탈방지 표준근로계약서', 5, null],
        'deposit_service_agreement' => ['보증금 및 서비스지원 약정서', 6, null],
    ];

    public function run(): void
    {
        // 이 시더는 마이그레이션에서도 불린다. file 컬럼이 생기기 전 시점에 실행될 수
        // 있으므로 컬럼이 있을 때만 값을 넣는다(없으면 뒤 마이그레이션이 채운다).
        $hasFile = Schema::hasColumn('required_documents', 'file');

        foreach (self::DOCUMENTS as $code => [$title, $order, $file]) {
            $attributes = [
                // 본문은 비워 둔다 — 콘솔에서 입력할 때까지 동의 화면에 제목만 보인다.
                'translations' => ['ko' => ['title' => $title, 'body' => '']],
                'version' => 1,
                'sort_order' => $order,
                'required' => true,
                // 항상 꺼진 채로 만든다. 켜는 순간 아직 동의하지 않은 근로자 전원이
                // 앱에서 막히므로(EnsureRequiredDocumentsAgreed), 그 시점은 운영이
                // 정해야 한다. 콘솔 [필수 동의 문서]에서 내용을 확인한 뒤 켠다.
                'active' => false,
            ];

            if ($hasFile) {
                $attributes['file'] = $file;
            }

            RequiredDocument::firstOrCreate(['code' => $code], $attributes);
        }
    }
}
