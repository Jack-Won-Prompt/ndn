<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Events\AdminAlertBroadcast;
use App\Domains\Support\Models\SosAlert;
use App\Domains\Support\Notifications\SosAlertedNotification;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

        // 관리자 앱 푸시 — 콘솔을 열어 두지 않은 시간대(야간·주말)에 이게 유일한 통지다.
        // 알림 때문에 SOS 접수 자체가 실패하면 안 되므로 예외를 삼킨다.
        try {
            $this->notifyAdmins();
        } catch (\Throwable $e) {
            Log::warning('SOS 관리자 푸시 실패', ['error' => $e->getMessage()]);
        }

        return $alert;
    }

    /**
     * NDN 관리자 전원에게 긴급 푸시.
     *
     * 시청·농가는 제외한다 — 24시간 대응 책임은 NDN 에 있고(§8), 관할 밖 SOS 까지
     * 모든 담당자를 깨울 이유가 없다.
     */
    private function notifyAdmins(): void
    {
        $admins = User::query()
            ->role(UserRole::NdnAdmin->value)
            ->get();

        Notification::send($admins, new SosAlertedNotification);
    }
}
