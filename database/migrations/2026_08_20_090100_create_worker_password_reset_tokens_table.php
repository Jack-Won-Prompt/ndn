<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 비밀번호 재설정 토큰.
 *
 * 관리자·협력사(users)와 표를 나눈다. 같은 표를 쓰면 이메일이 겹칠 때
 * 한쪽 재설정이 다른 쪽 토큰을 지워 버린다 — 실제로 근로자와 담당자가
 * 같은 주소를 쓰는 일이 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_password_reset_tokens');
    }
};
