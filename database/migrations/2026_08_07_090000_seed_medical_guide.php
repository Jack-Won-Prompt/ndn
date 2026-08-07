<?php

declare(strict_types=1);

use Database\Seeders\WorkerGuideSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * 의료비 지원 병원 안내를 채운다 — 배포(migrate --force)만으로 반영되도록.
 *
 * 시더는 key 로 맞춰 넣으므로 이미 있는 자료가 늘어나지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new WorkerGuideSeeder)->run();
    }

    public function down(): void
    {
        // 표는 create 마이그레이션이 되돌린다. 여기서 따로 지울 것은 없다.
    }
};
