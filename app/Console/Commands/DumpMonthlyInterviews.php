<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 월별 인터뷰 기록을 파일로 덤프한다 (폐기 전 백업).
 *
 * 월별 점검 6항목은 한국 생활 체크리스트와 근무상태 종합 점검표로 대체되며
 * 표는 폐기된다. 폐기 마이그레이션이 지우기 직전에 이 명령을 부른다 —
 * 되돌릴 수 없는 작업이라 운영에서 사고가 나면 이 파일이 유일한 근거다.
 *
 * 모델을 쓰지 않고 쿼리 빌더로 읽는다. 폐기 뒤에는 모델이 없어지므로
 * 그때도 이 명령이 그대로 동작해야 한다(있으면 덤프, 없으면 조용히 넘어감).
 */
class DumpMonthlyInterviews extends Command
{
    protected $signature = 'monthly-interviews:dump {--path= : 저장할 파일 경로 (기본: storage/app/archive/)}';

    protected $description = '월별 인터뷰 기록을 JSON 파일로 덤프한다 (표 폐기 전 백업)';

    public function handle(): int
    {
        $path = self::dump($this->option('path'));

        if ($path === null) {
            $this->info('monthly_interviews 표가 없습니다. 덤프할 것이 없습니다.');

            return self::SUCCESS;
        }

        $this->info('덤프 완료: '.$path);

        return self::SUCCESS;
    }

    /**
     * 표 전체를 JSON 파일로 남기고 경로를 돌려준다.
     *
     * 표가 없으면 null. 행이 없어도 파일은 남긴다 — '비어 있었다'는 것도 기록이다.
     */
    public static function dump(?string $path = null): ?string
    {
        if (! Schema::hasTable('monthly_interviews')) {
            return null;
        }

        $rows = DB::table('monthly_interviews')->orderBy('id')->get();

        $path ??= storage_path('app/archive/monthly-interviews-'.now()->format('Ymd-His').'.json');

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, json_encode([
            'table' => 'monthly_interviews',
            'dumped_at' => now()->toIso8601String(),
            'count' => $rows->count(),
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $path;
    }
}
