<?php

declare(strict_types=1);

use App\Domains\Support\Enums\TicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 민원 (CLAUDE.md §5, 업무흐름 §8).
 *
 * 근로자가 앱에서 발신(문제신고/문의/연장/조기귀국). NDN 담당자 배정·처리.
 * 주의(§7-2): 위치 컬럼 없음.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('status', 20)->default(TicketStatus::Open->value)->index();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['worker_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
