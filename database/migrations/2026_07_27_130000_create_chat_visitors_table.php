<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 회사소개 사이트의 익명 방문자(문의 채팅) 식별자.
 * 방문자는 로그인하지 않으므로 브라우저 쿠키의 token 으로만 식별한다.
 * 대화는 기존 chat_conversations 에 a_type='visitor', a_id=chat_visitors.id 로 저장 →
 * NDN 관리자는 콘솔 채팅 화면에서 그대로 응대한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_visitors', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();          // 쿠키에 저장되는 무작위 식별 토큰
            $table->string('name')->nullable();             // 방문자가 선택적으로 남긴 이름
            $table->string('locale', 10)->default('ko');    // 사이트 선택 언어 (자동번역 기준)
            $table->string('first_page')->nullable();       // 최초 문의 페이지(referer)
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_visitors');
    }
};
