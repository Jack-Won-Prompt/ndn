<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 면접 평가 체크리스트 항목 — 관리자가 콘솔에서 추가·수정·삭제한다.
 *
 * 이전에는 코드에 4개 항목이 상수로 박혀 있어(CandidateAdminController::CRITERIA)
 * 현장에서 항목을 바꾸려면 배포가 필요했다. 항목을 DB 로 옮겨 운영 중에도 조정한다.
 *
 * InterviewEvaluation.scores 는 이 테이블의 key 를 키로 하는 배열로 저장된다.
 * 항목을 지워도 지난 평가의 scores 는 그대로 남는다(당시 기준의 증빙).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_items', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();     // scores 배열의 키 (예: health)
            $table->string('label', 100);            // 화면 표시명
            $table->string('hint', 200)->nullable(); // 평가자용 판단 기준 한 줄
            $table->unsignedSmallInteger('max_score')->default(20);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_items');
    }
};
