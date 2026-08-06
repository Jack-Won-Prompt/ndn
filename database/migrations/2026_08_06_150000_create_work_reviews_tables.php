<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 외국인근로자 근무상태 종합 점검표.
 *
 * 원본: storage/app/worker-documents/work-status-review.docx.
 * 점검자가 현장에서 근로자 한 사람에 대해 작성한다. 농가 방문(FarmVisit)에 묶을 수
 * 있지만 따로 작성해도 된다 — 수시·특별점검은 방문 없이도 이뤄진다.
 *
 * 원본 §2(농가 정보)·§3(근로자 기본정보)은 여기에 옮겨 담지 않는다.
 * 이미 farms·workers 에 있는 값이고, 특히 여권번호는 암호화 대상이라(§7-1)
 * 점검표로 복사해 두면 같은 값이 두 곳에 남는다. 화면에서 이어 붙여 보여 준다.
 *
 * §9 의 위치(GPS) 기록도 여기 두지 않는다 — 점검자 좌표는 inspection_checkins
 * 한 곳에만 존재한다(§7-2). 사진도 농가 방문 사진을 그대로 쓴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_review_items', function (Blueprint $table) {
            $table->id();

            /** attendance / performance / community / safety */
            $table->string('section', 20);

            /** 코드로 참조한다 — 문구가 바뀌어도 지난 응답이 살아 있게. */
            $table->string('code', 40)->unique();

            $table->string('label', 200);

            /**
             * '확인'이 나쁜 신호인 항목인가 (안전·보건 영역에만 해당).
             *
             * 대부분은 확인되지 않은 쪽이 문제다(안전교육 실시 여부 등). 그러나
             * '임금 체불 여부'·'건강 이상 여부'는 확인된 쪽이 문제다. 이 구분이
             * 없으면 리스크가 정반대로 매겨진다.
             */
            $table->boolean('adverse')->default(false);

            /**
             * 리스크 점수에 넣을 항목인가.
             *
             * '최근 병원 진료 여부' 처럼 어느 쪽도 좋고 나쁨이 아닌 항목이 있다.
             * 점수에 넣으면 병원에 다녀온 사람이 전부 위험으로 잡히거나, 반대로
             * 진료 이력을 확인하지 못한 것이 위험으로 잡힌다. 기록만 남긴다.
             */
            $table->boolean('scored')->default(true);

            $table->unsignedSmallInteger('sort_order')->default(0);

            /** 끄면 점검 화면에서 빠진다. 지난 응답은 남는다. */
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['section', 'sort_order']);
        });

        Schema::create('work_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained();
            $table->foreignId('inspector_user_id')->constrained('users');

            /** 농가 방문에 묶어 작성한 경우. 없으면 단독 점검이다. */
            $table->foreignId('farm_visit_id')->nullable()->constrained()->nullOnDelete();

            // §1 점검 개요
            $table->dateTime('reviewed_at');
            $table->string('place', 200)->nullable();
            $table->string('review_type', 20);

            // §4 연장근무 내역
            $table->boolean('overtime_done')->nullable();
            $table->decimal('overtime_hours', 5, 1)->nullable();
            $table->boolean('overtime_consented')->nullable();

            // §8 임금 및 계약사항
            /** 개인 급여액이라 암호화한다(§7-1). 집계에는 쓰지 않는다. */
            $table->text('avg_monthly_wage')->nullable();
            $table->date('last_paid_on')->nullable();
            $table->boolean('wage_unpaid')->default(false);
            $table->boolean('board_provided')->nullable();
            $table->boolean('contract_followed')->nullable();
            $table->text('contract_violation')->nullable();

            // §10 종합 의견
            $table->string('result', 24);
            $table->text('notable')->nullable();
            $table->text('improvements')->nullable();
            $table->text('farm_requests')->nullable();

            // §11 향후 조치사항
            $table->date('action_due_on')->nullable();
            $table->string('action_assignee', 100)->nullable();
            $table->date('recheck_on')->nullable();
            $table->boolean('report_city')->default(false);
            $table->boolean('report_immigration')->default(false);
            $table->text('action_note')->nullable();

            // §12 확인 및 서명 — 서명한 사람의 이름. 서명 이미지는 아직 받지 않는다.
            $table->string('signed_inspector', 100)->nullable();
            $table->string('signed_farm', 100)->nullable();
            $table->string('signed_worker', 100)->nullable();
            $table->string('signed_interpreter', 100)->nullable();

            /** 응답에서 산출한 이탈 리스크. 근거는 RecordWorkReviewAction 에 있다. */
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->string('risk_level', 10)->default('low');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['worker_id', 'reviewed_at']);
            $table->index('risk_level');
        });

        Schema::create('work_review_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_review_item_id')->constrained();

            /** high / mid / low (3단계) 또는 yes / no (확인·미확인) */
            $table->string('value', 8);

            $table->string('note', 300)->nullable();

            $table->timestamps();

            $table->unique(['work_review_id', 'work_review_item_id'], 'work_review_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_review_answers');
        Schema::dropIfExists('work_reviews');
        Schema::dropIfExists('work_review_items');
    }
};
