<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 가입 신청 선발 결과 (업무흐름 §2 — 모집·선발).
 *
 * `workers.status` 는 계정이 살아 있는지(로그인 가능한지)만 말한다. 그것만으로는
 * 승인 대기 줄에 선 사람들을 구분할 수 없다 — 이제 막 들어온 신청과, 서류가
 * 부족해 보완을 요청해 둔 신청과, 면접 결과를 기다리는 보류가 전부 `pending` 이다.
 *
 * 그래서 계정 상태와 **선발 진행 상태**를 갈라 둔다. 합격/불합격은 계정 상태로도
 * 드러나지만(active/rejected) 여기에도 남긴다 — 언제 누가 판단했는지가 함께 있어야
 * 나중에 되짚을 수 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            // ScreeningStatus — null 이면 '접수'(아직 아무도 손대지 않음)
            $table->string('screening_status', 20)->nullable()->after('approved_by');
            // 보류·불합격 사유, 보완 요청 메모
            $table->text('screening_note')->nullable()->after('screening_status');
            $table->timestamp('screened_at')->nullable()->after('screening_note');
            $table->foreignId('screened_by')->nullable()->after('screened_at')
                ->constrained('users')->nullOnDelete();

            // 보완을 요청한 항목 — 근로자가 링크로 들어왔을 때 무엇을 채워야 하는지 보여 준다
            $table->json('supplement_items')->nullable()->after('screened_by');
            $table->timestamp('supplement_requested_at')->nullable()->after('supplement_items');

            $table->index('screening_status');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropForeign(['screened_by']);
            $table->dropIndex(['screening_status']);
            $table->dropColumn([
                'screening_status', 'screening_note', 'screened_at', 'screened_by',
                'supplement_items', 'supplement_requested_at',
            ]);
        });
    }
};
