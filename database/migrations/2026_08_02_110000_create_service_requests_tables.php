<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SR(Service Request) — 콘솔 사용자가 올리는 시스템 개선·오류 요청.
 *
 * 근로자 민원(support_tickets)과는 별개다. 이쪽은 운영자가 NDN 개발/운영팀에
 * 요청하는 내부 창구이며, 담당자가 답글을 달고 상태를 종료까지 관리한다.
 *
 * 주의(§7-2): 위치정보 컬럼을 두지 않는다.
 * 주의(§7-3): 본문은 사용자가 자유 입력하므로 이메일 알림에 싣지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();

            // 등록자 — 완료 알림 이메일 수신자
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('title', 200);
            $table->text('body');

            // 접수 / 처리중 / 적용 완료 / 반려 (ServiceRequestStatus)
            $table->string('status', 20)->default('received')->index();

            // 담당자 — 첫 답글 작성자가 자동 배정된다
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('service_request_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['service_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_replies');
        Schema::dropIfExists('service_requests');
    }
};
