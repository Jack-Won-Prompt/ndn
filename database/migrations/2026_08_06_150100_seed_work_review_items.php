<?php

declare(strict_types=1);

use Database\Seeders\WorkReviewItemSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * 근무상태 종합 점검표 항목 43가지를 채운다 —
 * 배포(migrate --force)만으로 반영되도록.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new WorkReviewItemSeeder)->run();
    }

    public function down(): void
    {
        // 표는 create 마이그레이션이 되돌린다. 여기서 따로 지울 것은 없다.
    }
};
