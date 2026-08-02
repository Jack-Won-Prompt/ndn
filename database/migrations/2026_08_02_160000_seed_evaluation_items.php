<?php

declare(strict_types=1);

use Database\Seeders\EvaluationItemSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * 평가 체크리스트 초안 6항목을 채운다 — 배포(migrate --force)만으로 반영되도록.
 *
 * 항목이 하나도 없으면 평가 자체가 불가능하므로 기본값이 반드시 있어야 한다.
 * 이미 있는 key 는 건드리지 않는다(운영에서 조정한 배점 보존).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new EvaluationItemSeeder)->run();
    }

    public function down(): void
    {
        // 운영에서 조정한 항목이 날아가면 안 되므로 롤백에서 지우지 않는다.
    }
};
