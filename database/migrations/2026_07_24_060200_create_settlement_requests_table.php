<?php

declare(strict_types=1);

use App\Domains\Settlement\Enums\SettlementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 정착 서비스 신청 (CLAUDE.md §5).
 *
 * assigned_agency_id 는 이 건을 처리하도록 배정된 제휴 대리점. partner_agency 스코프의 기준.
 * 주의(§7-2): 위치 컬럼 없음.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->nullable(); // Worker 모델은 후속 슬라이스에서 추가
            $table->string('type', 20)->default(SettlementType::Bank->value);
            $table->unsignedBigInteger('assigned_agency_id')->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_requests');
    }
};
