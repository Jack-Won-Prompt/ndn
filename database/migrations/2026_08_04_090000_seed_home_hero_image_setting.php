<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * 홈 히어로 배경 사진을 설정에 채운다 — 배포(migrate --force)만으로 반영되도록.
 *
 * 사진 자체는 저장소에 있지만 표시 여부는 설정값(site.hero_image)이 정한다.
 * 로컬에서만 값을 넣어 두면 운영에서는 사진 없이 그라디언트만 나온다(실제로 그랬다).
 *
 * 이미 값이 있으면 덮어쓰지 않는다 — 관리자가 [사이트 설정]에서 다른 사진으로
 * 바꿨거나 일부러 비워 둔 것을 되돌리면 안 된다.
 */
return new class extends Migration
{
    private const DEFAULT_HERO = 'harvest.jpg';

    public function up(): void
    {
        // 키 자체가 없을 때만 채운다. 관리자가 비워 둔 것(빈 문자열)은 그대로 존중한다.
        if (! Setting::query()->where('key', 'site.hero_image')->exists()) {
            Setting::put('site.hero_image', self::DEFAULT_HERO);
        }
    }

    public function down(): void
    {
        // 화면 설정은 롤백해도 유지한다.
    }
};
