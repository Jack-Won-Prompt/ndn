<?php

declare(strict_types=1);

use App\Domains\Onboarding\Models\RequiredDocument;
use Database\Seeders\RequiredDocumentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * 필수 문서 틀을 다시 맞춘다.
 *
 * 근로 동의서(work_consent)가 시더에는 있는데 실제 DB 에는 없는 환경이 있다.
 * 그 문서를 추가한 마이그레이션(2026_08_06_090000)은 이미 실행된 것으로 기록돼
 * 있어 다시 돌지 않으므로, 그 환경은 손대지 않으면 영영 비어 있다.
 *
 * 시더는 firstOrCreate 라 이미 있는 문서와 입력해 둔 본문은 건드리지 않는다.
 * 어느 환경에서 돌려도 같은 상태로 수렴한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RequiredDocumentSeeder)->run();

        // 파일이 비어 있으면 채운다. 이미 다른 파일을 붙여 뒀으면 그대로 둔다.
        RequiredDocument::query()
            ->where('code', 'work_consent')
            ->whereNull('file')
            ->update(['file' => 'work-consent.pdf']);
    }

    public function down(): void
    {
        // 문서 틀과 입력해 둔 본문을 지우지 않는다.
    }
};
