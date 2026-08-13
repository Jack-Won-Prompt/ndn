<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 조기 귀국 결정 · 이탈·연락두절 상태 (업무흐름 §8).
 *
 * 지금까지 근로자가 계약을 못 채우고 빠지면 `workers.status` 를 손으로 바꾸는
 * 것 말고는 남는 게 없었다. **왜 빠졌는지·누가 결정했는지·언제 나갔는지**가
 * 어디에도 없어서 지자체 보고를 매번 기억으로 채웠다.
 *
 * ※ 위치 컬럼을 두지 않는다(§7-2). 이탈자를 찾는 기능은 위치 추적이 아니라
 *   마지막 연락일·경위 기록으로 한다. lat/lng 는 요청받아도 만들지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_exits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            // 어느 배정에서 빠졌는가. 배정이 지워져도 사건 기록은 남긴다.
            $table->foreignId('placement_id')->nullable()->constrained()->nullOnDelete();
            // 앱에서 올라온 조기 귀국 신청과의 연결 (있을 때만)
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 20);      // WorkerExitType
            $table->string('status', 20);    // WorkerExitStatus
            $table->string('reason', 20);    // WorkerExitReason
            $table->text('reason_detail')->nullable();

            // 유형에 따라 뜻이 다르다: 조기 귀국=신청일, 이탈=마지막 연락일
            $table->date('occurred_on');
            // 이탈을 인지한 날 (연락이 끊긴 날과 다르다)
            $table->date('noticed_on')->nullable();

            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();

            // 실제 출국일 — 예정과 다를 수 있어 따로 받는다
            $table->date('departed_on')->nullable();

            // 출입국·경찰 신고 여부. 이탈 확정 건은 신고가 따라붙는다.
            $table->boolean('reported')->default(false);
            $table->date('reported_on')->nullable();
            $table->string('report_ref', 100)->nullable();  // 접수번호

            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['worker_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_exits');
    }
};
