<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 조직 주체(시청·농가·송출·대리점) 초대 (초대 전용 가입).
 *
 * 관리자가 이메일+역할로 초대를 발송하면 수신자가 링크에서 계정을 설정한다.
 * token 은 평문이 아닌 해시(sha256 hex)만 저장한다. 상태는 필드로부터 파생한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('name')->nullable();                 // 초대 대상 이름(선택)
            $table->string('role', 40);                         // UserRole value
            $table->unsignedBigInteger('assigned_agency_id')->nullable(); // 대리점 배정(partner_agency)
            $table->string('token', 64)->unique();              // sha256(평문) hex
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
