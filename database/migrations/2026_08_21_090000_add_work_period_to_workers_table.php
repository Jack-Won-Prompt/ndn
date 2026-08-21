<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자의 근로 기간 (입국일 ~ 종료일).
 *
 * 배정(placements)에도 기간이 있지만 뜻이 다르다. 그쪽은 **그 농가에서 일하는**
 * 기간이고 수요에서 온다 — 한 사람이 A 농가에서 두 달, B 농가에서 한 달 일할 수
 * 있다. 이쪽은 **그 사람의 체류·근로 예정 기간**이고, 배정이 정해지기 훨씬 전에
 * 지자체 명단으로 먼저 들어온다.
 *
 * 실제로 받은 명단이 그랬다 — 52명 전원에게 입국일과 '3개월/5개월/8개월' 이
 * 적혀 있는데 배정은 아직 하나도 없었다. 배정에만 기간을 두면 이 값을 적어 둘
 * 곳이 없어 담당자가 따로 엑셀을 들고 다니게 된다.
 *
 * 명단은 종료일 대신 개월수를 주는 경우가 많아, 없으면 시작일에서 계산해 넣는다
 * (엑셀 서식의 EDATE(입국일, 개월수) - 1 과 같은 셈).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->date('work_start_date')->nullable()->after('note');
            $table->date('work_end_date')->nullable()->after('work_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn(['work_start_date', 'work_end_date']);
        });
    }
};
