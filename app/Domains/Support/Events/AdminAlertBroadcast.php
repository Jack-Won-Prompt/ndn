<?php

declare(strict_types=1);

namespace App\Domains\Support\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * NDN 관리자 콘솔 실시간 알림 (Pusher). 주요 "조치 필요" 이벤트를 관리자 전원에게 푸시한다.
 *
 * kind: inquiry(새 문의) | signup(가입 대기) | sos(긴급) — 콘솔이 토스트 + 사이드바 배지를 갱신.
 * screen: 배지를 올릴 사이드바 메뉴 키(inquiries|signups|tickets…).
 *
 * §7-3 준수: message 에는 개인정보(이름·전화·여권 등)를 넣지 않는다. 건수·유형 안내만.
 */
class AdminAlertBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $kind,
        public string $message,
        public string $screen,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.alerts')];
    }

    public function broadcastAs(): string
    {
        return 'admin.alert';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return [
            'kind' => $this->kind,
            'message' => $this->message,
            'screen' => $this->screen,
        ];
    }
}
