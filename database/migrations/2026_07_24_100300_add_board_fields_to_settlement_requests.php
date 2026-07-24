<?php

declare(strict_types=1);

use App\Domains\Settlement\Enums\SettlementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 정착 처리보드(칸반) 필드 추가 (업무흐름 §6-3).
 * SLA 기한·배정일시·완료일시. 기존 status 값을 칸반 단계로 정규화.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_requests', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('assigned_agency_id');
            $table->timestamp('sla_due_at')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('sla_due_at');
        });

        // 기존 'pending' 등 비칸반 값을 '접수'로 정규화
        DB::table('settlement_requests')
            ->whereNotIn('status', array_map(fn ($s) => $s->value, SettlementStatus::cases()))
            ->update(['status' => SettlementStatus::Received->value]);
    }

    public function down(): void
    {
        Schema::table('settlement_requests', function (Blueprint $table) {
            $table->dropColumn(['assigned_at', 'sla_due_at', 'completed_at']);
        });
    }
};
