<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자가 지원한 지자체 (가입 시 선택).
 *
 * 계절근로자 프로그램은 지자체(시군)별로 모집 정원·조건이 따로 운영되므로,
 * 어느 지역에 지원했는지를 가입 시점에 확정해 둔다. 실제 배치 지역은
 * Placement → Farm → City 로 따로 확정되며, 이 값은 지원 지역이다.
 *
 * 기존 행은 알 수 없으므로 nullable 로 두고 관리자가 콘솔에서 지정한다.
 * 지역별 집계(지원자 수·정원 대비)에 쓰이므로 인덱스를 건다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('nationality')
                ->constrained('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
