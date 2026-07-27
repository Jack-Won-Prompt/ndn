<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 접속 로그에 로그인 ID(이메일)를 함께 남긴다.
 *
 * 웹 접속 로그는 관리자·시청·농가·송출·대리점 등 User 계정만 대상이며, 이메일은 로그인
 * 식별자다(근로자는 앱 API 를 쓰므로 여기 잡히지 않는다). 계정 삭제 후에도 조회되도록
 * 로그 시점의 이메일을 비정규화 저장한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->string('actor_email')->nullable()->after('actor');
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropColumn('actor_email');
        });
    }
};
