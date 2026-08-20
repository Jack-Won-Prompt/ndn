<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 골라서 보낸 공지의 수신자 (업무흐름 §8).
 *
 * **'근로자 선택' 공지에만 쌓는다.** 전체·국적별처럼 범위로 정한 공지는
 * `notices.recipients_count` 로 충분하고, 96명에게 보낼 때마다 96줄을 남기면
 * 표가 공지 이력이 아니라 발송 로그가 된다.
 *
 * 고른 공지는 다르다 — "왜 이 사람만 받았나" 를 나중에 되짚어야 하고, 그때
 * 필요한 것은 숫자가 아니라 이름이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['notice_id', 'worker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_recipients');
    }
};
