<?php

declare(strict_types=1);

use Database\Seeders\WorkerGuideSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * 안내 자료 본문을 채운다 — 배포(migrate --force)만으로 반영되도록.
 *
 * 시더는 배포 시 자동 실행되지 않아 운영 앱의 정보 화면이 비게 된다.
 * 내용은 원본 문서에서 옮긴 것이라 콘솔 입력을 기다릴 필요가 없다.
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
