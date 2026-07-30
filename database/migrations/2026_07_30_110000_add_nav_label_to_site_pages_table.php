<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 하단 탭에 쓸 짧은 이름.
 *
 * title 은 화면 제목이라 문장이 될 수 있다("모집부터 귀국까지 하나의 데이터로
 * 관리합니다"). 그대로 탭에 넣으면 라벨이 뭉개진다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->string('nav_label', 20)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn('nav_label');
        });
    }
};
