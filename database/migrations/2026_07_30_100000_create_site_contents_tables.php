<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 회사소개 콘텐츠 (모바일 앱 네이티브 화면용).
 *
 * 지금까지 회사소개 문구·이미지는 블레이드 템플릿에 직접 박혀 있었다. 앱을
 * 네이티브로 만들면 같은 내용을 앱에도 복제해야 하고, 문구 한 줄 고칠 때마다
 * 스토어 심사를 거쳐 앱을 다시 배포해야 한다.
 *
 * 내용을 DB 로 옮겨 앱이 API 로 받아 간다. 웹과 앱이 같은 원본을 본다.
 *
 * 화면 생김새는 담지 않는다 — 웹은 웹의 레이아웃으로, 앱은 앱의 디자인으로
 * 같은 내용을 그린다. 그래서 섹션은 '유형 + 내용' 만 가진다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();

            // home / about / services / worker / partners / contact
            $table->string('key', 32)->unique();

            $table->string('title');
            $table->string('lead', 500)->nullable();

            /** 상단 배경 이미지 (public 기준 상대 경로). */
            $table->string('hero_image')->nullable();

            $table->unsignedSmallInteger('position')->default(0);

            /** 앱 하단 탭에 노출할지. 끄면 API 에서 빠진다. */
            $table->boolean('in_app_nav')->default(true);

            /** 앱 하단 탭 아이콘 이름 (Flutter Icons 매핑용). */
            $table->string('icon', 40)->nullable();

            $table->timestamps();
        });

        Schema::create('site_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_page_id')->constrained()->cascadeOnDelete();

            /** split / cards / steps / stats / checks / faq / table / cta / rich */
            $table->string('type', 24);

            $table->unsignedSmallInteger('position')->default(0);

            /**
             * 유형별 내용.
             *
             *   eyebrow  string   작은 머리말
             *   title    string
             *   body     string[] 문단
             *   image    string   이미지 경로
             *   items    array[]  카드·단계·지표·문답 등 반복 항목
             *
             * 스키마를 유형마다 테이블로 쪼개면 화면 하나 추가할 때마다
             * 마이그레이션이 필요해진다. 내용은 자유 형식으로 두고 유형이
             * 해석을 정한다.
             */
            $table->json('payload');

            $table->timestamps();

            $table->index(['site_page_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_sections');
        Schema::dropIfExists('site_pages');
    }
};
