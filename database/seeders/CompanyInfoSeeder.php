<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * 회사(사업자) 기본 정보 시드 — 사업자등록증 기준(공개 정보).
 *
 * 개인정보처리방침·문의 페이지·푸터 등에 표시된다. 전자상거래법상 사이트에 공개되는
 * 정보(상호·대표자·사업자번호·주소)이므로 기본값으로 둔다.
 *
 * 이미 값이 있으면 덮어쓰지 않는다 — 관리자가 콘솔 [사이트 설정]에서 수정한 값을 보존한다.
 * 운영·로컬 공통으로 실행된다(DatabaseSeeder). 전화·이메일은 등록증에 없어 비워 둔다.
 */
class CompanyInfoSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'company.ceo' => '전병언',
            'company.biz_no' => '771-88-02980',
            'company.address' => '경기도 김포시 양촌읍 대곶남로580번길 55, 가동',
            'contact.address' => '경기도 김포시 양촌읍 대곶남로580번길 55, 가동',
        ];

        foreach ($defaults as $key => $value) {
            // 비어 있을 때만 채운다(관리자 수정값 보존).
            if (blank(Setting::get($key))) {
                Setting::put($key, $value);
            }
        }
    }
}
