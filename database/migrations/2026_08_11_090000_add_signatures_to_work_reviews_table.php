<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 근무상태 종합 점검표 §12 «확인 및 서명» — 서명 이미지.
 *
 * 지금까지는 서명한 사람의 이름만 문자열로 받았다. 원본은 이름 옆에 서명란이
 * 있고, 이 점검표는 관할 지자체·출입국이 요청하면 제출하는 자료다(원본 각주).
 * 이름만 적힌 표는 증빙이 되지 않는다.
 *
 * 파일은 private 저장소에 두고 경로만 남긴다(§9). 서명은 본인을 특정하는
 * 개인정보라 public/ 에 두지 않으며, 인증된 라우트로만 나간다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_reviews', function (Blueprint $table) {
            // 이름(signed_*)은 그대로 두고 서명 파일 경로를 나란히 붙인다.
            $table->string('signature_inspector', 200)->nullable()->after('signed_interpreter');
            $table->string('signature_farm', 200)->nullable()->after('signature_inspector');
            $table->string('signature_worker', 200)->nullable()->after('signature_farm');
            $table->string('signature_interpreter', 200)->nullable()->after('signature_worker');
        });
    }

    public function down(): void
    {
        Schema::table('work_reviews', function (Blueprint $table) {
            $table->dropColumn([
                'signature_inspector',
                'signature_farm',
                'signature_worker',
                'signature_interpreter',
            ]);
        });
    }
};
