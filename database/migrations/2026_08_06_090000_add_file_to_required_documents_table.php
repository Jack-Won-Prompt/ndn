<?php

declare(strict_types=1);

use App\Domains\Onboarding\Models\RequiredDocument;
use Database\Seeders\RequiredDocumentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 필수 문서에 원본 파일을 붙인다.
 *
 * 근로 동의서처럼 서명해야 하는 서식은 화면에 옮겨 적는 대신 원본을 그대로 받게 한다.
 * 옮겨 적으면 문안이 원본과 달라질 위험이 있고, 그건 법적 문서에서 사고가 된다.
 *
 * 값은 storage/app/worker-documents/ 안의 파일명이다. public/ 에 두지 않는다 —
 * 근로자 개인 대상 문서이므로 인증 라우트로만 내보낸다(§9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('required_documents', function (Blueprint $table) {
            $table->string('file', 120)->nullable()->after('translations');
        });

        // 문서 틀을 만드는 시더는 이 컬럼이 생기기 전에 이미 돌았다(2026_08_02_140000).
        // 그때 만들어진 행에는 파일이 비어 있으므로 여기서 채운다.
        // 시더를 한 번 더 돌려 새로 추가된 문서(work_consent)도 만든다.
        (new RequiredDocumentSeeder)->run();

        // 켜지는 않는다 — 켜는 순간 미동의 근로자 전원이 앱에서 막힌다.
        RequiredDocument::query()
            ->where('code', 'work_consent')
            ->whereNull('file')
            ->update(['file' => 'work-consent.pdf']);
    }

    public function down(): void
    {
        Schema::table('required_documents', function (Blueprint $table) {
            $table->dropColumn('file');
        });
    }
};
