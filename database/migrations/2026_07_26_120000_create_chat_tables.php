<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 채팅(대화·메시지) — 시청·농가·NDN·근로자·해외협력사 간 2자 대화.
 * 각 대화는 두 참여자(a, b)를 비정규화 저장. 참여자 유형: ndn|city|farm|worker|agency.
 * 메시지는 원문(body/body_lang) + 상대 언어 자동번역(translated_body/translate_lang) 저장.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 24)->index();          // 예: ndn_worker, city_farm

            $table->string('a_type', 10);                  // ndn|city|farm|worker|agency
            $table->unsignedBigInteger('a_id')->nullable();
            $table->string('a_lang', 10)->default('ko');

            $table->string('b_type', 10);
            $table->unsignedBigInteger('b_id')->nullable();
            $table->string('b_lang', 10)->default('ko');

            $table->unsignedBigInteger('worker_id')->nullable()->index(); // 근로자 대화 검색용

            $table->timestamp('a_last_read_at')->nullable();
            $table->timestamp('b_last_read_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();

            $table->index(['a_type', 'a_id']);
            $table->index(['b_type', 'b_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->char('sender_side', 1);                // 'a' | 'b'
            $table->text('body');                          // 작성자 원문
            $table->string('body_lang', 10);
            $table->text('translated_body')->nullable();   // 상대 표시 언어로 자동번역
            $table->string('translate_lang', 10)->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
