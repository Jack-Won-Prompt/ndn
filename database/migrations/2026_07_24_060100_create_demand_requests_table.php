<?php

declare(strict_types=1);

use App\Domains\Demand\Enums\DemandStatus;
use App\Shared\Enums\Gender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 농가 수요 신청 (CLAUDE.md §5: 국적, 인원, 나이대, 성별, 형제동반 여부, 품목, 기간).
 *
 * 주의(§7-2): 이 테이블에는 위치정보(lat/lng) 컬럼을 두지 않는다.
 * 근로자 위치는 InspectionCheckin 과 SosAlert 두 곳에만 존재할 수 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_requests', function (Blueprint $table) {
            $table->id();

            // 신청 주체
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();

            // 수요 내용
            $table->string('nationality', 2);                 // 희망 송출국 (ISO-3166 alpha-2)
            $table->unsignedSmallInteger('headcount');        // 요청 인원
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->string('gender', 10)->default(Gender::Any->value);
            $table->boolean('allow_siblings')->default(false); // 형제/가족 동반 허용
            $table->string('crop', 100);                       // 품목
            $table->date('period_start');
            $table->date('period_end');
            $table->text('note')->nullable();

            // 상태
            $table->string('status', 20)->default(DemandStatus::Draft->value)->index();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['nationality', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_requests');
    }
};
