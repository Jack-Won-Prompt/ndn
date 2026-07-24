<?php

declare(strict_types=1);

use App\Domains\Onboarding\Enums\OnboardingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 셀프 온보딩 제출물 (CLAUDE.md §5, §7).
 *
 * 근로자가 본인 정보를 직접 기입한다. payload 는 개인정보를 포함하므로 애플리케이션
 * 레벨에서 암호화 저장(§7-1). 서명 파일은 private 스토리지 경로만 보관(§9).
 * 주의(§7-2): 위치 컬럼 없음.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();

            // 본인 기입 정보(주소·비상연락처 등). 암호화 저장.
            $table->text('payload')->nullable();

            // 전자서명 파일의 private 스토리지 경로 (서명 URL 로만 접근)
            $table->string('signature_path')->nullable();

            $table->string('status', 20)->default(OnboardingStatus::Draft->value)->index();

            // 검수
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_submissions');
    }
};
