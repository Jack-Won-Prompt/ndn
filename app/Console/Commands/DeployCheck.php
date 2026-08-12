<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Shared\Support\DeployState;
use Illuminate\Console\Command;

/**
 * 배포가 제대로 끝났는지 점검한다.
 *
 * 한 세션에 같은 원인으로 장애 보고가 세 번 왔다 — 코드만 올라가고 마이그레이션이
 * 돌지 않았거나, 라우트 캐시가 옛것으로 남아 있었다. deploy.sh 를 거치면 나지
 * 않는 일이지만, 급할 때 손으로 git pull 만 하는 일이 실제로 벌어진다.
 *
 * 배포 스크립트 마지막에서 부르고, 콘솔도 같은 판정을 띠로 띄운다.
 */
class DeployCheck extends Command
{
    protected $signature = 'ndn:deploy-check';

    protected $description = '미적용 마이그레이션·캐시 상태를 점검한다 (배포 마무리 확인)';

    public function handle(): int
    {
        $problems = DeployState::problems(fresh: true);

        // 캐시는 설정만 봐서는 알 수 없다. 실제로 써 보고 확인한다.
        $cacheOk = DeployState::cacheWritable();
        if (! $cacheOk && ! in_array(DeployState::CACHE_PROBLEM, $problems, true)) {
            $problems[] = DeployState::CACHE_PROBLEM;
        }

        if ($problems === []) {
            $this->info('배포 상태 정상 — 미적용 마이그레이션 없음, 캐시 저장소 정상.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error('✗ '.$problem);
        }

        $this->newLine();
        $this->line('  해결 순서 (순서가 중요하다):');
        $this->line('    php artisan migrate --force');
        $this->line('    php artisan optimize');
        $this->newLine();
        $this->line('  캐시를 먼저 지우면 라우트만 살아나고 테이블이 없어 다른 이유로 500 이 난다.');

        if (! $cacheOk) {
            $this->newLine();
            $this->line('  캐시 저장소 — 지금 설정은 <comment>'.config('cache.default').'</comment> 다.');
            $this->line('    database : cache 표가 있어야 한다 → php artisan migrate --force');
            $this->line('    file     : 웹 서버 사용자가 storage/ 에 쓸 수 있어야 한다');
            $this->line('               → chown -R www-data:www-data storage bootstrap/cache');
            $this->line('  이 프로젝트의 기본은 database 다(.env 의 CACHE_STORE).');
        }

        return self::FAILURE;
    }
}
