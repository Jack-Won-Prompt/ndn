<?php

declare(strict_types=1);

use Database\Seeders\LifeChecklistSeeder;
use Database\Seeders\WorkerGuideSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * 체크리스트 12항목과 «한국 생활 수칙» 안내 자료를 채운다 —
 * 배포(migrate --force)만으로 반영되도록.
 *
 * 안내 자료 시더를 다시 돌리는 것은 «한국 생활 수칙» 이 이번에 추가돼서다.
 * 시더는 key 로 맞춰 넣으므로 이미 있는 자료가 늘어나지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new LifeChecklistSeeder)->run();
        (new WorkerGuideSeeder)->run();
    }

    public function down(): void
    {
        // 표는 create 마이그레이션이 되돌린다. 여기서 따로 지울 것은 없다.
    }
};
