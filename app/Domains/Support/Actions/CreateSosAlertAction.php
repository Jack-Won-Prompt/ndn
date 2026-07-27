<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Events\AdminAlertBroadcast;
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
        $alert = SosAlert::create([
            'worker_id' => $worker->id,
            'lat' => $lat,
            'lng' => $lng,
            'alerted_at' => now(),
            'status' => 'open',
        ]);

        // 관리자 콘솔 실시간 긴급 알림 (개인정보 없이, §7-3). 실패해도 무시.
        try {
            broadcast(new AdminAlertBroadcast('sos', '긴급 SOS가 접수되었습니다.', 'tickets'));
        } catch (\Throwable $e) {
            // Pusher 미설정/실패 시 무시
        }

        return $alert;
    }
}
