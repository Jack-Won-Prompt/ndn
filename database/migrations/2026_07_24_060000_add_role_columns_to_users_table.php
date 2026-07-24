<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 근로자 알림/문서 렌더링에 쓰는 locale (CLAUDE.md §6). 기본 한국어.
            $table->string('locale', 5)->default('ko')->after('email');

            // 제휴 대리점 사용자가 배정된 대리점 id. partner_agency 스코프의 기준 (§7-5).
            // 대리점 테이블은 Settlement 도메인에서 별도 생성하므로 여기서는 FK 없이 인덱스만.
            $table->unsignedBigInteger('assigned_agency_id')->nullable()->after('locale');
            $table->index('assigned_agency_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['assigned_agency_id']);
            $table->dropColumn(['locale', 'assigned_agency_id']);
        });
    }
};
