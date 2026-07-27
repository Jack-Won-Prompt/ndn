<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 성별 — 매칭 조건 대조용 (업무흐름 §1·§4).
 *
 * demand_requests.gender 로 농가가 성별 조건을 낼 수 있는데 workers 에는 대응
 * 컬럼이 없어, 매칭 추천이 성별 조건을 조용히 무시하고 있었다.
 *
 * 암호화하지 않는다: 매칭 쿼리에서 WHERE 로 걸러야 하고(§7-1 의 암호화 대상 목록에도
 * 없음), 이름·국적과 같은 수준의 식별 정보다. 로그 노출은 다른 필드와 함께
 * MasksSensitiveData 대상이 아니므로 화면 노출 범위로만 통제한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('nationality')->index();
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropIndex(['gender']);
            $table->dropColumn('gender');
        });
    }
};
