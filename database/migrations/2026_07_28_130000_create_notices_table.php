<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 공지사항 (관리자 작성 → 근로자 FCM 푸시 + 인앱 알림).
 *
 * title/body 는 작성 원문(한국어)이며, 발송·조회 시 근로자 언어로 자동 번역한다(§6).
 * §7-3: 발송 전 개인정보 패턴 검증으로 본문에 개인정보가 섞이지 않게 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('target', 20)->default('all');   // all|nationality|status
            $table->string('target_value')->nullable();      // 예: 'BD', 'active'
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
