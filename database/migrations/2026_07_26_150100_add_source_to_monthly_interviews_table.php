<?php

declare(strict_types=1);

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
 *
 * ※ 이 표는 2026-08-06 에 폐기됐다(생활 체크리스트·근무상태 종합 점검표로 대체).
 *   기본값을 Enum 대신 문자열로 적은 것은 그 Enum 이 이제 없어서다 —
 *   지난 마이그레이션은 새로 만든 DB 에서도 그대로 실행돼야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_interviews', function (Blueprint $table) {
            $table->string('source', 10)
                ->default('inspector')
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
