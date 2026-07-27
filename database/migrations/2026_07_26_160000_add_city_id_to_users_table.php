<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 시청 담당자(city_officer)의 소속 지자체.
 *
 * 농가(farm_owner)는 farms.owner_user_id 로 자기 농가를 찾을 수 있지만, 시청 담당자는
 * 소속을 나타내는 연결이 없어 "관할 지자체 건만 조회" 스코프를 걸 수 없었다.
 * 관리자 API 의 역할별 스코프(PortalScope)가 이 컬럼을 기준으로 동작한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('assigned_agency_id')
                ->constrained('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
