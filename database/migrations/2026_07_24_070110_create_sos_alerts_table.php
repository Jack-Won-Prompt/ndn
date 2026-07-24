<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 긴급 SOS (CLAUDE.md §5, §7-2, §9).
 *
 * 위치정보가 허용되는 두 번째(마지막) 테이블. 좌표는 근로자가 SOS 버튼을 누른
 * "그 순간" 1회 전송된 값이며, 상시/주기 위치 수집이 아니다. 좌표는 SOS 요청
 * 본문으로만 수신한다(§9). LocationFieldGuardTest 화이트리스트에 있음.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();

            // SOS 발신 순간 좌표 1회 (§7-2 허용)
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->timestamp('alerted_at');
            $table->string('status', 20)->default('open'); // open / acknowledged / closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_alerts');
    }
};
