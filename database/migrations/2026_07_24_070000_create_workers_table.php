<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 (CLAUDE.md §5, §7).
 *
 * 민감 필드(passport_no, birth_date, phone_home_country)는 애플리케이션 레벨에서
 * encrypted cast 로 저장한다 → 컬럼은 암호문을 담으므로 text 로 넉넉히 잡는다(§7-1).
 * 검색이 필요한 여권번호는 blind index(passport_no_bidx) 해시 컬럼을 병행한다.
 *
 * 주의(§7-2): 이 테이블에는 위치정보(lat/lng)를 두지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();

            // 비민감
            $table->string('name');                 // 이름 (로그에서는 마스킹 대상)
            $table->string('nationality', 2);       // ISO-3166 alpha-2
            $table->string('locale', 5)->default('ko'); // 알림/문서 언어 (§6)
            $table->string('status', 20)->default('active');

            // 민감 — 암호문 저장 (평문 아님)
            $table->text('passport_no')->nullable();
            $table->text('birth_date')->nullable();
            $table->text('phone_home_country')->nullable();

            // 여권번호 검색용 blind index (HMAC-SHA256 hex = 64자)
            $table->string('passport_no_bidx', 64)->nullable()->unique();

            $table->timestamps();
            $table->softDeletes();

            // 파기 스케줄(§7-7)용: 소프트삭제 후 90일 경과분 조회를 위한 인덱스
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
