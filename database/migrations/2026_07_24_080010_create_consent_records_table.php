<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 동의 이력 (CLAUDE.md §5, §7-4).
 *
 * 목적별·기관별·항목별로 "행을 분리" 저장한다. 철회 시각(revoked_at)을 포함하며,
 * 철회는 행을 지우지 않고 revoked_at 을 채워 이력을 보존한다.
 * 제3자 제공(대리점 등)은 이 테이블에 활성(미철회) 동의가 있어야 Policy 를 통과한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();

            $table->string('purpose', 40);              // ConsentPurpose (목적별)
            $table->string('agency_type', 30)->nullable(); // 기관 유형 (기관별)
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->string('item', 40);                 // 동의 항목 (항목별: passport_no 등)

            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // 동일 (근로자·목적·기관·항목) 조합의 활성 동의를 빠르게 조회
            $table->index(['worker_id', 'purpose', 'agency_type', 'item'], 'consent_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
