<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 점검자 방문 체크인 (CLAUDE.md §5, §7-2).
 *
 * 위치정보(lat/lng)가 허용되는 두 테이블 중 하나. 여기 좌표는 "점검자"의 방문
 * 증빙이며 근로자의 상시 위치가 아니다. LocationFieldGuardTest 의 화이트리스트에 있음.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignId('inspector_user_id')->nullable()->constrained('users')->nullOnDelete();

            // 점검자 방문 좌표 (§7-2 허용)
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            $table->timestamp('checked_in_at');
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_checkins');
    }
};
