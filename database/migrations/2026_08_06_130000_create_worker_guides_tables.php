<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근로자 안내 자료 (앱 정보 화면).
 *
 * 사전교육 자료·긴급 연락처·의료기관 안내처럼 **읽기만 하는** 내용을 담는다.
 * 동의를 받는 문서는 required_documents 로 따로 있다 — 그쪽은 법적 문안이라
 * 언어별 원문을 콘솔에서 직접 넣지만, 여기는 교육·안내 자료라 한국어 원문
 * 하나만 두고 근로자 언어로 실시간 번역해 내보낸다(회사소개 콘텐츠와 같은 방식).
 *
 * 원본 docx 는 storage/app/worker-documents/ 에 그대로 있고, 이 표는 그 내용을
 * 앱이 화면으로 그릴 수 있게 구조화한 것이다. 원본을 대체하지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_guides', function (Blueprint $table) {
            $table->id();

            // pre-training / emergency / medical
            $table->string('key', 32)->unique();

            $table->string('title');
            $table->string('lead', 500)->nullable();

            /** 앱 목록의 아이콘 이름 (Flutter Icons 매핑용). */
            $table->string('icon', 40)->nullable();

            $table->unsignedSmallInteger('position')->default(0);

            /** 끄면 앱 목록에서 빠진다. 내용을 다듬는 동안 숨겨 둘 수 있다. */
            $table->boolean('active')->default(true);

            $table->timestamps();
        });

        Schema::create('worker_guide_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_guide_id')->constrained()->cascadeOnDelete();

            /** text / list / table / qa / contacts / steps */
            $table->string('type', 24);

            $table->unsignedSmallInteger('position')->default(0);

            /**
             * 유형별 내용.
             *
             *   heading  string   섹션 제목
             *   intro    string   머리말 한 문단
             *   body     string   본문 (text)
             *   items    array    목록·문답·연락처·행동요령 항목
             *   columns  string[] 표 머리행 (table)
             *   rows     array[]  표 본문 (table)
             *   note     string   ※ 로 붙는 꼬리말
             *
             * 유형마다 테이블을 쪼개면 안내 한 종류 늘 때마다 마이그레이션이
             * 필요해진다. 내용은 자유 형식으로 두고 type 이 해석을 정한다.
             */
            $table->json('payload');

            $table->timestamps();

            $table->index(['worker_guide_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_guide_sections');
        Schema::dropIfExists('worker_guides');
    }
};
