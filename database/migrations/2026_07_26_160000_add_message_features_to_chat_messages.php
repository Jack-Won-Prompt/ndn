<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 채팅 메시지 기능 확장 (supportworks 이식): 첨부파일·답장·수정·삭제.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();           // 파일만 있는 메시지 허용

            $table->string('file_path')->nullable()->after('translate_lang');
            $table->string('file_name')->nullable()->after('file_path');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_name');
            $table->string('file_mime', 100)->nullable()->after('file_size');

            $table->foreignId('reply_to_id')->nullable()->after('file_mime')
                ->constrained('chat_messages')->nullOnDelete();

            $table->timestamp('edited_at')->nullable()->after('reply_to_id');
            $table->timestamp('deleted_at')->nullable()->after('edited_at');   // 소프트 표시(행은 유지)
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn(['file_path', 'file_name', 'file_size', 'file_mime', 'edited_at', 'deleted_at']);
        });
    }
};
