<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 농가 경영체등록번호.
 *
 * 지자체에 계절근로자 배정을 신청할 때 농업경영체 등록번호를 함께 적어 낸다.
 * 그동안은 콘솔에 자리가 없어 담당자가 따로 적은 엑셀을 들고 다녔고, 그러면
 * 농가 정보가 두 군데로 갈라진다.
 *
 * 사업자 식별번호이지 개인 식별번호가 아니라 §7-1 의 암호화 대상은 아니다.
 * 목록·엑셀에서 그대로 보고 찾아야 하는 값이기도 하다.
 *
 * 이미 등록된 농가는 번호를 모르므로 nullable 로 둔다 — 필수로 걸면 기존 농가를
 * 손대는 순간 저장이 막힌다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->string('business_reg_no', 30)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropColumn('business_reg_no');
        });
    }
};
