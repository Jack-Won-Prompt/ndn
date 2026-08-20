<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 배정·입국 기록에 소프트 삭제.
 *
 * 농가는 soft delete 인데 배정은 아니었다. 그래서 농가를 지워도 배정이 그대로
 * 남았고, DB 의 cascadeOnDelete 는 실제 행이 지워질 때만 도는 것이라 아무 일도
 * 하지 않았다. 결과는 두 가지다.
 *
 *   - 배정 현황·지역별 배치·근로자 앱에 **없는 농가**에 매인 줄이 계속 보인다
 *   - 그 근로자는 '이미 배정됨' 으로 잡혀 다른 농가에 넣을 수 없다
 *
 * 배정을 완전히 지우지 않고 soft delete 로 두는 이유는, 누가 어디에 배정됐다가
 * 어떻게 정리됐는지가 증빙으로 남아야 하기 때문이다(업무흐름 §4). 농가를 되살리면
 * 배정도 함께 되살릴 수 있다.
 *
 * 입국 기록은 배정 확정 때 만들어지므로 같은 운명을 따른다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('placements', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('arrival_records', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('placements', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('arrival_records', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
