<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOS 대응 증빙 (업무흐름 §7 — 긴급 24시간 대응).
 *
 * 기존에는 status 문자열만 있어 "누가 언제 확인했는지" 기록이 없었다. 긴급 건이
 * 방치되지 않았음을 증빙하려면 확인자·확인 시각이 필요하다.
 *
 * 주의(§7-2): 이 테이블의 lat/lng 는 SOS 발신 순간 좌표로 이미 허용된 것이며,
 * 여기에 추가 위치 컬럼을 만들지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sos_alerts', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable()->after('status');
            $table->foreignId('acknowledged_by')->nullable()->after('acknowledged_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('acknowledged_by');
            $table->text('note')->nullable()->after('closed_at');

            // 상황판은 미확인 건을 최신순으로 훑는다
            $table->index(['status', 'alerted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sos_alerts', function (Blueprint $table) {
            $table->dropIndex(['status', 'alerted_at']);
            $table->dropForeign(['acknowledged_by']);
            $table->dropColumn(['acknowledged_at', 'acknowledged_by', 'closed_at', 'note']);
        });
    }
};
