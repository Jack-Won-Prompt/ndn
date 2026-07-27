<?php

declare(strict_types=1);

use App\Domains\Arrival\Enums\ArrivalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 입국·이송 (업무흐름 §5).
 *
 * 배정(placements) 1건당 입국 기록 1건. 항공편·픽업·농가 인계까지의 진행을 담는다.
 *
 * 주의(§7-2): **이 테이블에는 위치정보(lat/lng)를 두지 않는다.** 픽업·인계는
 * "언제 확인했는가"(타임스탬프)와 "누가 확인했는가"(담당자)로만 증빙하며,
 * 근로자의 좌표는 남기지 않는다. 위치는 inspection_checkins·sos_alerts 에만 존재한다.
 *
 * 서류는 확인 여부(bool)만 JSON 으로 담는다. 여권 사본 등 실제 파일은 온보딩의
 * private 스토리지에만 두고 여기로 복사하지 않는다 (§7-1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arrival_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->unique()
                ->constrained('placements')->cascadeOnDelete();

            $table->string('status', 20)->default(ArrivalStatus::Scheduled->value)->index();

            // 항공 정보
            $table->string('flight_no', 20)->nullable();
            $table->string('airport', 60)->nullable();       // 예: 인천(ICN)
            $table->timestamp('scheduled_arrival_at')->nullable()->index();

            // 픽업 담당자(직원). 근로자가 아니라 담당 User 다.
            $table->foreignId('pickup_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('vehicle_no', 20)->nullable();

            // 단계별 실제 확인 시각 — 증빙
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();

            // 서류 체크리스트 (ArrivalDocument 키 → bool)
            $table->json('documents')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arrival_records');
    }
};
