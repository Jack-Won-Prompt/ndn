<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 개인 서류 (본사가 보관).
 *
 * 여권 사본·건강검진 결과처럼 **사람마다 다른** 서류다. 전원 공통 서식은
 * required_documents 쪽이다.
 *
 * 본사가 현지 인력을 직접 가입시킬 때 이 서류들을 함께 올린다. 지금까지는
 * 근로자가 앱에서 올리는 온보딩 서명 말고는 개인 서류를 둘 자리가 없었다.
 *
 * 파일은 private 저장이고 경로만 남긴다(§9). 여권 사본은 그 자체로 민감정보라
 * public/ 에 두지 않으며 인증 라우트로만 나가고, 열면 기록을 남긴다(§7-6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_files', function (Blueprint $table) {
            $table->id();

            // 근로자가 파기되면(§7-7) 서류도 함께 사라져야 한다.
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();

            /** passport / visa / health / contract / criminal / photo / other */
            $table->string('type', 20);

            /** 디스크 기준 경로. 파일명은 ASCII 로 짓는다(한글은 서버·백업에서 깨진다). */
            $table->string('path', 255);

            /** 올릴 때의 원래 이름 — 화면에 그대로 보여 준다. */
            $table->string('original_name', 255);

            $table->unsignedInteger('size')->default(0);
            $table->string('mime', 120)->nullable();

            /** 만료가 있는 서류(비자·건강검진)의 유효기간. 없으면 null. */
            $table->date('expires_on')->nullable();

            $table->string('note', 300)->nullable();

            /** 누가 올렸는지 — 개인 서류라 책임 소재가 남아야 한다. */
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['worker_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_files');
    }
};
