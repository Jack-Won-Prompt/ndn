<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 푸시 알림 수신 기기 등록 (FCM).
 *
 * 근로자(Worker)와 관리자(User)가 같은 앱을 쓰므로 소유자를 다형 관계로 둔다.
 * 한 사람이 여러 기기를 쓸 수 있고, 한 기기를 여러 사람이 번갈아 쓸 수도 있어
 * **토큰이 유일 키**다. 재로그인 시 같은 토큰의 소유자를 갈아끼운다 —
 * 그렇지 않으면 기기를 넘겨받은 사람에게 이전 사용자의 알림이 간다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();

            // 소유자 — Worker 또는 User
            $table->morphs('tokenable');

            // FCM 등록 토큰. 기기·앱 재설치마다 새로 발급된다.
            $table->string('token', 512)->unique();

            $table->string('platform', 16)->default('android');

            // 알림을 어느 언어로 보낼지. 근로자는 5개 언어를 쓴다(§6).
            $table->string('locale', 8)->default('ko');

            // 진단용 — 특정 버전에서만 나는 문제를 추적할 때 쓴다.
            $table->string('app_version', 32)->nullable();

            // 마지막으로 앱이 이 토큰을 갱신·확인한 시각. 오래된 토큰 정리 기준.
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            // "이 사람의 기기 목록" 조회가 발송 때마다 일어난다.
            $table->index(['tokenable_type', 'tokenable_id', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
