<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 정착 서비스 처리 증빙 문서 (대리점이 업로드). private 저장, 다운로드 시 대리점명 워터마크(§7-5).
 * 처리 메모(partner_note)는 settlement_requests 에 함께 보관.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_request_id')->constrained('settlement_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('uploaded_by')->nullable();   // User id (대리점 담당자)
            $table->string('disk_path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index('settlement_request_id');
        });

        Schema::table('settlement_requests', function (Blueprint $table) {
            $table->text('partner_note')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('settlement_requests', function (Blueprint $table) {
            $table->dropColumn('partner_note');
        });
        Schema::dropIfExists('settlement_documents');
    }
};
