<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\SosAlert;

/**
 * 긴급 SOS 생성 (CLAUDE.md §7-2, §9).
 *
 * 좌표는 근로자가 SOS 버튼을 누른 "그 순간" 요청 본문으로만 수신한 값이며, 이 한 번의
 * 기록 외에 위치를 저장·폴링하지 않는다.
 */
class CreateSosAlertAction
{
    public function execute(Worker $worker, ?float $lat = null, ?float $lng = null): SosAlert
    {
        return SosAlert::create([
            'worker_id' => $worker->id,
            'lat' => $lat,
            'lng' => $lng,
            'alerted_at' => now(),
            'status' => 'open',
        ]);
    }
}
