<?php

declare(strict_types=1);

use Database\Seeders\CompanyInfoSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * 회사(사업자) 기본 정보를 설정에 채운다 — 배포(migrate --force)만으로 반영되도록.
 *
 * 시더는 배포 시 자동 실행되지 않아 운영에서 개인정보처리방침·문의 페이지의 회사정보가
 * 비어 있었다(대표자·사업자번호·주소 = '—'). 데이터 마이그레이션으로 옮겨 표준 배포
 * 단계에서 채워지게 한다. 값이 이미 있으면 덮어쓰지 않는다(관리자 [사이트 설정] 수정값 보존).
 * Setting::put 이 설정 캐시를 무효화하므로 별도 캐시 삭제가 필요 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new CompanyInfoSeeder)->run();
    }

    public function down(): void
    {
        // 회사 정보는 파기하지 않는다(롤백해도 유지).
    }
};
