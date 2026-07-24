<?php

declare(strict_types=1);

use App\Domains\Recruitment\Enums\CandidateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 후보자 (CLAUDE.md §5, 업무흐름 §2).
 *
 * 송출국 모집 명단. 면접 후 합격/보류/불합격으로 분류. 보류자는 대기열 순번(queue_position).
 * 불합격자의 개인정보는 수집하지 않으므로 민감 필드를 두지 않는다(§2-3). 여권 등 민감정보는
 * 온보딩 단계(Worker)에서 수집·암호화한다.
 *
 * 주의(§7-2): 위치 컬럼 없음.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demand_request_id')->nullable()
                ->constrained('demand_requests')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('workers')->nullOnDelete();

            $table->string('name');
            $table->string('nationality', 2);
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 10)->nullable();

            $table->string('status', 20)->default(CandidateStatus::Applied->value)->index();
            $table->unsignedInteger('queue_position')->nullable(); // 보류 대기열 순번

            $table->timestamps();
            $table->index(['status', 'queue_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
