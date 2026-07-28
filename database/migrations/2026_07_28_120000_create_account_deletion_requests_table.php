<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 사용자 계정·데이터 삭제 요청 (Google Play 데이터 삭제 정책 준수).
 *
 * 공개 페이지(/account-deletion)에서 접수한 삭제 요청을 보관한다. 관리자가 확인 후
 * 실제 계정을 soft delete 하면, workers:purge-expired 잡이 90일 경과 시 민감 필드를
 * 파기한다(§7-7). 요청 자체에는 처리에 필요한 최소 정보(이름·이메일)만 저장한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();       // 가입 시 사용한 이메일(로그인 ID)
            $table->text('reason')->nullable();      // 요청 사유(선택)
            $table->string('status', 20)->default('pending'); // pending|completed|rejected
            $table->text('admin_note')->nullable();  // 처리 메모
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
