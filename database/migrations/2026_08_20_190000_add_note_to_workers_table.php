<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 비고.
 *
 * 담당자가 사람마다 적어 두는 메모다. 지금까지는 적을 곳이 없어 이름 뒤에
 * 괄호로 붙이거나 따로 엑셀을 들고 다녔고, 그러면 근로자 정보가 두 군데로 갈라진다.
 *
 * screening_note 와는 다르다 — 그쪽은 가입 심사 판단의 근거이고, 이쪽은 그 뒤
 * 계속 이어지는 업무 메모다. 한 칸에 섞으면 심사 사유가 메모에 묻힌다.
 *
 * 암호화하지 않는다. §7-1 이 지목한 것은 여권번호·생년월일·연락처·계좌번호이고,
 * 비고는 목록에서 눈으로 훑고 엑셀로 내려받아야 하는 값이다. 다만 이 칸에 적힌
 * 것도 개인정보로 다뤄야 하므로, 이 칸을 보여 주는 화면은 §7-6 열람 기록을 남긴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('phone_home_country');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
