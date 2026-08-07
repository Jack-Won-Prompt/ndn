<?php

declare(strict_types=1);

use App\Console\Commands\DumpMonthlyInterviews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 월별 인터뷰 6항목 폐기.
 *
 * 그 자리를 둘이 나눠 맡는다.
 *   - 근로자 쪽(source=self)  → 한국 생활 체크리스트 (life_checklist_*)
 *   - 점검자 쪽(source=inspector) → 근무상태 종합 점검표 (work_reviews)
 * 이탈 리스크의 근거도 근무상태 점검표로 옮겼다.
 *
 * **되돌릴 수 없다.** 지우기 전에 표 전체를 파일로 덤프한다 — 운영에서 사고가
 * 나면 그 파일이 유일한 근거다. down() 은 빈 표만 되살리며 내용은 돌아오지 않는다.
 * 덤프 파일에서 되살려야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('monthly_interviews')) {
            return;
        }

        $path = DumpMonthlyInterviews::dump();
        if ($path !== null) {
            // 배포 로그에 남겨 둔다. 나중에 어느 파일을 찾아야 하는지 알아야 한다.
            echo PHP_EOL.'  monthly_interviews 덤프: '.$path.PHP_EOL;
        }

        Schema::drop('monthly_interviews');
    }

    public function down(): void
    {
        // 되살리더라도 내용은 돌아오지 않는다. 스키마만 원래대로 둔다.
        if (Schema::hasTable('monthly_interviews')) {
            return;
        }

        Schema::create('monthly_interviews', function ($table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspector_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('inspection_checkin_id')->nullable();
            $table->unsignedBigInteger('farm_visit_id')->nullable();
            $table->date('interviewed_on');
            $table->string('source', 20)->default('inspector');
            $table->boolean('pay_received')->default(true);
            $table->boolean('no_discrimination')->default(true);
            $table->boolean('follows_rules')->default(true);
            $table->boolean('adapts_group')->default(true);
            $table->boolean('health_ok')->default(true);
            $table->boolean('no_flight_signs')->default(true);
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_level', 10)->default('low');
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }
};
