<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 한국 생활 체크리스트 — 입국 후 1주일 이내 확인사항.
 *
 * 원본: storage/app/worker-documents/life-checklist.docx 부록1.
 * 근로자가 앱에서 직접 체크하고, 관리자는 누가 무엇을 아직 확인하지 않았는지 본다.
 *
 * 매일 출근 전·숙소 생활·건강관리 목록은 여기에 넣지 않는다. 그쪽은 매일 반복하는
 * 습관이라 체크 기록을 쌓아도 쓸모가 없다 — 안내 자료(worker_guides)로 읽힌다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('life_checklist_items', function (Blueprint $table) {
            $table->id();

            /** 코드로 참조한다 — 문구가 바뀌어도 기존 체크가 살아 있게. */
            $table->string('code', 40)->unique();

            $table->string('label', 200);

            /** 왜 확인해야 하는지 한 줄. 비워 둘 수 있다. */
            $table->string('hint', 300)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            /** 끄면 근로자 화면에서 빠진다. 기존 체크 기록은 남는다. */
            $table->boolean('active')->default(true);

            $table->timestamps();
        });

        Schema::create('life_checklist_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('life_checklist_item_id')->constrained()->cascadeOnDelete();

            /** 확인한 시각. 근로자가 체크를 풀면 행째 지운다. */
            $table->timestamp('checked_at');

            $table->timestamps();

            // 한 근로자가 같은 항목을 두 번 체크할 수 없다.
            $table->unique(['worker_id', 'life_checklist_item_id'], 'life_checklist_worker_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('life_checklist_checks');
        Schema::dropIfExists('life_checklist_items');
    }
};
