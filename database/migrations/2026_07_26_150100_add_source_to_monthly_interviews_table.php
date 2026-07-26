<?php

declare(strict_types=1);

use App\Domains\Monitoring\Enums\InterviewSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로 생활 평가의 작성 주체 구분 (업무흐름 §7).
 *
 * 기존 행은 모두 점검자 방문 기록이므로 기본값 inspector 로 채워진다.
 * 근로자 앱에서 제출한 자가 평가는 self 로 저장되며 inspector_user_id 가 null 이다.
 *
 * 주의(§7-2): 이 테이블에는 위치 컬럼을 두지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_interviews', function (Blueprint $table) {
            $table->string('source', 10)
                ->default(InterviewSource::Inspector->value)
                ->after('interviewed_on')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('monthly_interviews', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
