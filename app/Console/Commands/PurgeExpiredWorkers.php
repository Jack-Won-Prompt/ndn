<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Recruitment\Models\Worker;
use Illuminate\Console\Command;

/**
 * 파기 스케줄 잡 (CLAUDE.md §7-7, 업무흐름 §9-4).
 *
 * soft delete 후 90일이 지난 근로자의 민감 필드를 null 처리한다(파기). 레코드 자체는
 * 이력을 위해 남기되, 여권번호·생년월일·전화번호와 blind index 를 제거한다.
 */
class PurgeExpiredWorkers extends Command
{
    protected $signature = 'workers:purge-expired {--days=90 : 파기 기준 경과일} {--dry-run : 실제 파기 없이 대상만 표시}';

    protected $description = 'soft delete 후 지정 일수 경과한 근로자의 민감 필드를 파기 (§7-7)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $targets = Worker::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->whereNotNull('passport_no') // 이미 파기된 건 제외
            ->get();

        if ($targets->isEmpty()) {
            $this->info("파기 대상 없음 (기준: {$days}일 경과).");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("[dry-run] 파기 대상 {$targets->count()}건 (실제 파기 안 함).");

            return self::SUCCESS;
        }

        $purged = 0;
        foreach ($targets as $worker) {
            $worker->forceFill([
                'passport_no' => null,
                'birth_date' => null,
                'phone_home_country' => null,
                'passport_no_bidx' => null,
            ])->saveQuietly();
            $purged++;
        }

        $this->info("민감 필드 파기 완료: {$purged}건 (soft delete 후 {$days}일 경과).");

        return self::SUCCESS;
    }
}
