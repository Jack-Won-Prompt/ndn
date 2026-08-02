<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 지역(시군)별 모집 조건 — 계절근로자는 지자체별로 배정 인원이 따로 정해진다.
 *
 * quota      : 이번 회차 모집 정원(명). null 이면 정원 제한 없음.
 * recruiting : 모집 중 여부. 끄면 가입 화면의 지역 선택지에서 빠진다.
 *
 * 정원이 차면 그 지역으로는 새 가입을 받지 않는다(RegisterWorkerRequest 에서 검증).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->unsignedInteger('quota')->nullable()->after('region');
            $table->boolean('recruiting')->default(true)->after('quota');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['quota', 'recruiting']);
        });
    }
};
