<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 웹 세션의 '로그인 유지' 용 토큰.
 *
 * 앱은 Sanctum 토큰이라 필요 없었지만, 웹 세션 가드는 이 칸이 없으면
 * remember me 가 조용히 동작하지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->rememberToken()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
