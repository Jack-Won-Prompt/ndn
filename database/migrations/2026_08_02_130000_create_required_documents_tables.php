<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 필수 확인·동의 문서 (근로자 의무사항·표준근로계약서·상해보험 약정서 등).
 *
 * 앱은 이 문서들에 모두 동의하기 전에는 다음 화면으로 넘어갈 수 없다
 * (EnsureRequiredDocumentsAgreed 미들웨어).
 *
 * translations : {"ko":{"title":..,"body":..}, "vi":{..}, ...} — 5개 언어(§6).
 *                본문은 법적 효력이 있는 문안이라 코드에 두지 않고 콘솔에서 입력한다.
 * version      : 문안이 바뀌면 올린다. 올리면 기존 동의는 무효가 되어 다시 받는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('required_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();       // worker_duties, standard_contract, ...
            $table->json('translations')->nullable();   // locale → {title, body}
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('required')->default(true); // false 면 열람만 하고 동의는 안 받는다
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('required_document_id')->constrained()->cascadeOnDelete();
            // 동의한 시점의 문서 버전 — 문안이 바뀌면(version 상승) 다시 받아야 한다
            $table->unsignedInteger('version');
            $table->timestamp('agreed_at');
            $table->timestamps();

            // 같은 근로자·문서·버전은 한 번만
            $table->unique(['worker_id', 'required_document_id', 'version'], 'doc_consent_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_consents');
        Schema::dropIfExists('required_documents');
    }
};
