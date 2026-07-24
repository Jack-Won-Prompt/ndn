<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 사이트 설정 키-값 저장소.
 * 회사소개 사이트의 편집 가능한 자리표시자 내용(통계·사업자정보·연락처 등)을 담는다.
 * 관리 콘솔의 "사이트 설정" 화면에서 수정한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
