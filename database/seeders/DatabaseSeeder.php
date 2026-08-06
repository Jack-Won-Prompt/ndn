<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 역할 6종 먼저 (계정에 부여하기 전). 운영·로컬 공통으로 항상 필요.
        $this->call(RoleSeeder::class);

        // 회사(사업자) 기본 정보 — 운영·로컬 공통. 값이 없을 때만 채운다(관리자 수정값 보존).
        $this->call(CompanyInfoSeeder::class);

        // 필수 확인·동의 문서 5종의 틀 — 본문은 콘솔에서 입력한다(운영·로컬 공통).
        $this->call(RequiredDocumentSeeder::class);

        // 면접 평가 체크리스트 초안 6항목 — 콘솔에서 조정한다(운영·로컬 공통).
        $this->call(EvaluationItemSeeder::class);

        // 근로자 안내 자료(사전교육·생활 수칙·긴급 연락처) — 원본 문서에서 옮긴 내용.
        $this->call(WorkerGuideSeeder::class);

        // 한국 생활 체크리스트 12항목 — 콘솔에서 문구를 조정한다(운영·로컬 공통).
        $this->call(LifeChecklistSeeder::class);

        // 근무상태 종합 점검표 43항목 — 현장 점검 화면이 이 목록으로 그려진다.
        $this->call(WorkReviewItemSeeder::class);

        // 운영(production)에서는 테스트 계정·데모 데이터를 만들지 않는다.
        // 관리자 계정은 별도로 생성한다: php artisan ndn:create-admin ...
        // 운영에서 테스트가 필요하면 아래 시더를 명시적으로 실행:
        //   php artisan db:seed --class=Database\\Seeders\\TestAccountsSeeder --force
        //   php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder --force
        //   php artisan db:seed --class=Database\\Seeders\\IntegrityTestSeeder --force  // 정합성 검증 20건+파일
        //   php artisan db:seed --class=Database\\Seeders\\ScreenDemoSeeder --force     // 로그인별 모든 화면 10건+파일
        if (app()->environment('production')) {
            return;
        }

        // 로컬/스테이징: 역할별 테스트 계정 + 데모 업무 데이터
        $this->call(TestAccountsSeeder::class);
        $this->call(DemoDataSeeder::class);
    }
}
