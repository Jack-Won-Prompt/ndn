<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * 배포가 덜 끝난 상태를 알아낸다.
 *
 * 운영에서 세 번 같은 장애가 났다. 증상은 매번 달랐지만(사진 안 나옴 / 500 /
 * 404) 원인은 하나였다 — 코드만 올라가고 마이그레이션이 돌지 않았다.
 * 그 상태를 사람이 알아채기 전에는 아무 데도 드러나지 않는 것이 문제였다.
 *
 * 배포 스크립트(ndn:deploy-check)와 콘솔 경고 띠가 이 판정을 함께 쓴다.
 */
class DeployState
{
    private const CACHE_KEY = 'deploy_state:problems';

    /**
     * 지금 배포가 덜 끝났다면 그 이유들. 정상이면 빈 배열.
     *
     * @return list<string>
     */
    public static function problems(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        // 콘솔이 화면마다 부르므로 짧게 캐시한다. 배포 직후 1분이면 사라진다.
        return Cache::remember(self::CACHE_KEY, now()->addMinute(), function () {
            $problems = [];

            $pending = self::pendingMigrations();
            if ($pending > 0) {
                $problems[] = "적용되지 않은 마이그레이션이 {$pending}개 있습니다. "
                    .'화면이 500 이나 404 로 깨질 수 있습니다.';
            }

            return $problems;
        });
    }

    /**
     * 아직 돌지 않은 마이그레이션 개수.
     *
     * migrations 표를 직접 읽는다 — Artisan::call('migrate:status') 는 출력이
     * 사람용이라 파싱이 깨지기 쉽다.
     */
    public static function pendingMigrations(): int
    {
        try {
            if (! Schema::hasTable('migrations')) {
                // 표조차 없으면 최초 설치가 안 끝난 것이다. 개수는 파일 수 전부.
                return count(self::migrationFiles());
            }

            $ran = DB::table('migrations')->pluck('migration')->all();

            return count(array_diff(self::migrationFiles(), $ran));
        } catch (Throwable) {
            // DB 를 못 읽는 상황이라면 이 경고보다 큰 문제가 이미 드러난다.
            return 0;
        }
    }

    /** @return list<string> 파일명(확장자 제외) */
    private static function migrationFiles(): array
    {
        return collect(File::files(database_path('migrations')))
            ->filter(fn ($f) => $f->getExtension() === 'php')
            ->map(fn ($f) => $f->getBasename('.php'))
            ->values()
            ->all();
    }
}
