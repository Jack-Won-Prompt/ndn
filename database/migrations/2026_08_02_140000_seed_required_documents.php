<?php

declare(strict_types=1);

use Database\Seeders\RequiredDocumentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * 필수 확인·동의 문서 5종의 틀을 만든다 — 배포(migrate --force)만으로 반영되도록.
 *
 * 시더는 배포 시 자동 실행되지 않아 운영 콘솔에 문서 목록이 비어 있게 된다.
 * 본문은 비어 있고 active=false 라 근로자에게 노출되지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RequiredDocumentSeeder)->run();
    }

    public function down(): void
    {
        // 입력해 둔 문안이 날아가면 안 되므로 롤백에서 지우지 않는다.
    }
};
