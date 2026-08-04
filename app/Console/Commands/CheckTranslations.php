<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 근로자 대상 번역 키 누락 검사 (CLAUDE.md §6).
 *
 * ko 를 기준으로, 근로자 대상 네임스페이스의 모든 키가 bn/lo/si/vi 에도
 * 존재하는지 확인한다. 하나라도 빠지면 실패(exit 1) → CI 에서 PR 반려 근거.
 *
 * "근로자 대상"만 5개 언어 필수이므로 검사 대상 네임스페이스를 명시적으로 한정한다.
 * (demand.php 등 관리자 포털용 파일은 대상이 아니다)
 */
class CheckTranslations extends Command
{
    protected $signature = 'translations:check';

    protected $description = '근로자 대상 번역 키가 지원 언어 전부에 존재하는지 검사';

    /** 근로자 대상 네임스페이스 (파일명, 확장자 제외) */
    private const WORKER_FACING = ['worker'];

    private const BASE_LOCALE = 'ko';

    private const REQUIRED_LOCALES = ['ko', 'bn', 'lo', 'si', 'vi', 'ne', 'ky'];

    public function handle(): int
    {
        $langPath = base_path('lang');
        $problems = [];

        foreach (self::WORKER_FACING as $namespace) {
            $baseFile = "{$langPath}/".self::BASE_LOCALE."/{$namespace}.php";

            if (! is_file($baseFile)) {
                $problems[] = "기준 파일 없음: {$namespace}.php (".self::BASE_LOCALE.')';

                continue;
            }

            $baseKeys = $this->flatKeys(require $baseFile);

            foreach (self::REQUIRED_LOCALES as $locale) {
                $file = "{$langPath}/{$locale}/{$namespace}.php";

                if (! is_file($file)) {
                    $problems[] = "파일 누락: {$locale}/{$namespace}.php";

                    continue;
                }

                $keys = $this->flatKeys(require $file);
                $missing = array_diff($baseKeys, $keys);
                $extra = array_diff($keys, $baseKeys);

                foreach ($missing as $key) {
                    $problems[] = "키 누락: {$locale}/{$namespace}.php → '{$key}'";
                }
                foreach ($extra as $key) {
                    $problems[] = "기준에 없는 키: {$locale}/{$namespace}.php → '{$key}'";
                }
            }
        }

        if ($problems !== []) {
            $this->error('번역 검사 실패:');
            foreach ($problems as $p) {
                $this->line("  - {$p}");
            }

            return self::FAILURE;
        }

        // 언어가 늘어날 수 있으므로 개수를 문구에 박지 않는다.
        $this->info(sprintf(
            '번역 검사 통과: 근로자 대상 키가 %d개 언어(%s)에 모두 존재합니다.',
            count(self::REQUIRED_LOCALES),
            implode('/', self::REQUIRED_LOCALES),
        ));

        return self::SUCCESS;
    }

    /**
     * 중첩 배열을 점(.) 표기 평면 키 목록으로.
     *
     * @param  array<string, mixed>  $array
     * @return array<int, string>
     */
    private function flatKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $k => $v) {
            $full = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
            if (is_array($v)) {
                $keys = array_merge($keys, $this->flatKeys($v, $full));
            } else {
                $keys[] = $full;
            }
        }

        return $keys;
    }
}
