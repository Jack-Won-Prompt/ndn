<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 월별 인터뷰를 농가 방문 점검(FarmVisit)에 연결.
 *
 * 본사 방문 시 근로자 개개인의 6항목 인터뷰를 해당 방문에 묶어 기록·이력 관리한다.
 * 근로자 자가 평가(source=self)는 farm_visit_id 가 null 이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_interviews', function (Blueprint $table) {
            $table->foreignId('farm_visit_id')->nullable()->after('inspection_checkin_id')
                ->constrained('farm_visits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('monthly_interviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farm_visit_id');
        });
    }
};
