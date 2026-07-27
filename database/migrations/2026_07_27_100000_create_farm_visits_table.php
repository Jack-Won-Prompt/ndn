<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 본사 월별 농가 방문 점검 (FarmVisit) + 현장 사진(FarmVisitPhoto).
 *
 * 본사 담당자가 매월 농가를 방문해 농가 상태·근로자 근무 현황·애로사항·조치사항을 기록하고
 * 현장 사진을 여러 장 업로드한다. 위치정보(lat/lng)는 저장하지 않는다(§7-2).
 * 방문 증빙은 현장 사진(private 저장)으로 대체한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('visited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('visited_on');
            $table->string('farm_status', 20)->default('normal');    // 농가 상태: normal/caution/issue
            $table->string('worker_status', 20)->default('normal');  // 근로자 근무 상태: normal/caution/issue
            $table->unsignedSmallInteger('worker_headcount')->nullable(); // 재직 인원
            $table->text('work_note')->nullable();   // 근무 현황 상세
            $table->text('issue_note')->nullable();  // 애로사항
            $table->text('action_note')->nullable(); // 조치·후속사항
            $table->text('memo')->nullable();        // 종합 메모
            $table->timestamps();
            $table->softDeletes();

            $table->index(['farm_id', 'visited_on']);
        });

        Schema::create('farm_visit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_visit_id')->constrained('farm_visits')->cascadeOnDelete();
            $table->string('path');            // private 저장 경로 (평문 아님)
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime', 100)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_visit_photos');
        Schema::dropIfExists('farm_visits');
    }
};
