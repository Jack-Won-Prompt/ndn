<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근무상태 점검표 관계기관 공유 이력.
 *
 * 원본 서식 각주: "관할 지자체 및 관계기관의 요청 시 제출할 수 있습니다."
 * 제출은 개인정보 제3자 제공이라 **누가·언제·무엇을·어디로** 보냈는지 남아야 한다.
 *
 * 한 번의 발송(batch)에 점검표 여러 건 × 수신처 여러 곳이 들어가므로,
 * (발송 × 점검표 × 수신처) 조합마다 한 줄을 남긴다. 그래야 "이 점검표는
 * 어디로 나갔나" 와 "이 발송은 무엇을 담았나" 를 둘 다 되짚을 수 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_review_shares', function (Blueprint $table) {
            $table->id();
            // 한 번의 발송 묶음
            $table->uuid('batch_id')->index();
            $table->foreignId('work_review_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_email');
            // 기관명 — 어디에 냈는지가 이력의 핵심이다
            $table->string('recipient_org')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['work_review_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_review_shares');
    }
};
