<?php

declare(strict_types=1);

use App\Domains\Monitoring\Enums\RiskLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 월별 인터뷰 (CLAUDE.md §5, 업무흐름 §7).
 *
 * 점검자가 매월 농가 방문 시 근로자와 진행하는 6개 항목 점검. 응답은 boolean
 * (true = 정상/양호). 부정 응답 수로 이탈 리스크 스코어·등급을 산출한다.
 *
 * 주의(§7-2): 이 테이블에는 위치 컬럼을 두지 않는다. 방문 좌표는 연결된
 * inspection_checkins 에만 존재한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignId('inspector_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('inspection_checkin_id')->nullable()
                ->constrained('inspection_checkins')->nullOnDelete();

            $table->date('interviewed_on');

            // 6개 항목 (true = 양호/정상)
            $table->boolean('pay_received')->default(true);     // 급여 정상 수령
            $table->boolean('no_discrimination')->default(true); // 차별 없음
            $table->boolean('follows_rules')->default(true);     // 생활 규칙 준수
            $table->boolean('adapts_group')->default(true);      // 단체생활 적응
            $table->boolean('health_ok')->default(true);         // 건강 양호
            $table->boolean('no_flight_signs')->default(true);   // 이탈 징후 없음

            $table->unsignedTinyInteger('risk_score')->default(0); // 부정 신호 수 0~6
            $table->string('risk_level', 10)->default(RiskLevel::Low->value)->index();

            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['worker_id', 'interviewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_interviews');
    }
};
