<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 앱 로그인 자격증명 (CLAUDE.md §9).
 *
 * 근로자 앱은 Sanctum 토큰으로 인증하지만 토큰을 발급받을 경로가 없었다.
 * 이메일 + 비밀번호로 로그인해 토큰을 발급한다.
 *
 * - email 은 로그인 식별자이므로 검색이 필요하다 → 평문 + unique.
 *   (§7-1 의 암호화 대상 목록은 passport_no/birth_date/phone_home_country/계좌번호로,
 *    로그인 식별자는 포함되지 않는다. 로그 노출은 MasksSensitiveData 로 함께 가린다.)
 * - password 는 Laravel 의 hashed cast 로 단방향 해시 저장한다.
 *
 * 주의(§7-2): 이 테이블에는 위치정보(lat/lng)를 두지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('name');
            $table->string('password')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'password']);
        });
    }
};
