<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 접속·페이지 접근 로그 (운영 감사용).
 *
 * 회사소개(메인)의 비로그인 접속과 로그인 이후 콘솔·포털 페이지 접근을 모두 기록한다.
 * 위치정보는 저장하지 않는다(§7-2). IP 는 접속 감사 목적의 보안 로그로만 보관한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor')->nullable();          // 표시용 라벨(게스트 / 이름(역할))
            $table->string('method', 8);
            $table->string('path', 512);
            $table->string('route_name')->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referer', 512)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
