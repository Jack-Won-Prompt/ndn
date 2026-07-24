<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 면접 평가 (CLAUDE.md §5, 업무흐름 §2).
 *
 * NDN 면접관이 항목별 점수를 입력한다. 총점으로 합격/보류/불합격을 분류한다.
 * 평가 기록은 아카이브되어 다음 시즌 재지원 시 이력 조회에 쓰인다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('interviewer_user_id')->nullable()->constrained('users')->nullOnDelete();

            // 항목별 점수(0~10). 확장 대비 JSON.
            $table->json('scores')->nullable();
            $table->unsignedSmallInteger('total_score')->default(0);
            $table->string('result', 20); // CandidateStatus (passed/held/rejected)
            $table->text('comment')->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_evaluations');
    }
};
