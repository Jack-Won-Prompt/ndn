<?php

declare(strict_types=1);

use App\Domains\Matching\Enums\PlacementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 매칭 확정 — 근로자 ↔ 농가 (CLAUDE.md §5, 업무흐름 §4).
 *
 * 형제·가족 동반은 placement_group_id 를 공유해 함께 배치된다.
 * 관리자 앱의 역할별 스코프(PortalScope)가 이 테이블을 기준으로 동작한다.
 *
 * 주의(§7-2): 이 테이블에는 위치정보(lat/lng)를 두지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();

            // 형제·가족 그룹 매칭 (같은 값이면 같은 농가에 함께 배치)
            $table->uuid('placement_group_id')->nullable()->index();

            $table->string('status', 20)->default(PlacementStatus::Proposed->value)->index();

            // 근로 기간
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            // 변경·취소 사유 (증빙 원칙)
            $table->text('note')->nullable();

            $table->timestamps();

            // 한 근로자가 같은 농가에 중복 배정되지 않도록
            $table->unique(['worker_id', 'farm_id']);
            $table->index(['farm_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placements');
    }
};
