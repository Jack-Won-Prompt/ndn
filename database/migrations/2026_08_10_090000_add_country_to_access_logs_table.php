<?php

declare(strict_types=1);

use App\Shared\Support\IpCountry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 접속 로그에 접속 국가를 남긴다.
 *
 * 이미 저장하고 있는 IP 에서 판별한 값이고, 나라 단위까지만이다(IpCountry).
 * 좌표는 다루지 않는다 — 근로자 위치는 여전히 sos_alerts 와 inspection_checkins
 * 두 곳에만 있다(§7-2). 이건 '어디서 들어온 요청인가' 를 남기는 보안 감사용이다.
 *
 * 표시할 때 계산하지 않고 저장하는 이유: 판별표는 갱신되기 때문에 나중에 계산하면
 * 그때 표에 따라 과거 기록의 국가가 바뀐다. 접속 당시의 판단을 그대로 남긴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            // ISO-2 또는 'LOCAL'(사내·같은 장비). 판별 못 하면 null.
            $table->string('country', 5)->nullable()->after('ip')->index();
        });

        /*
         * 이미 쌓인 기록 중 **사설·루프백만** 채운다.
         *
         * 나라 판별은 판별표에 따라 달라지므로 과거 기록을 지금 표로 소급하지
         * 않는다. 그러나 '사설 대역이라 내부'라는 판단은 표와 무관하게 언제나
         * 같으므로 이건 채워도 과거를 왜곡하지 않는다.
         */
        DB::table('access_logs')
            ->whereNull('country')
            ->whereNotNull('ip')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $ids = [];
                foreach ($rows as $row) {
                    if (IpCountry::of($row->ip) === IpCountry::LOCAL) {
                        $ids[] = $row->id;
                    }
                }
                if ($ids !== []) {
                    DB::table('access_logs')->whereIn('id', $ids)
                        ->update(['country' => IpCountry::LOCAL]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropIndex(['country']);
            $table->dropColumn('country');
        });
    }
};
