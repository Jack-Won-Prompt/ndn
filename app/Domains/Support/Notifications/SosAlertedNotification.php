<?php

declare(strict_types=1);

namespace App\Domains\Support\Notifications;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 관리자에게 보내는 긴급 SOS 푸시 (업무흐름 §8 — 24시간 대응).
 *
 * 이 앱에서 가장 시간이 중요한 알림이다. 지금까지는 관리자가 앱을 열어 SOS
 * 화면에 들어가야만 접수 사실을 알 수 있었다.
 *
 * **누가 눌렀는지는 담지 않는다.** 잠금화면에 뜨는 데다 관리자 기기는 사무실에서
 * 공유되기도 한다. 이름이 필요하면 앱을 열어 SOS 화면에서 본다(그 열람은
 * §7-6 감사 로그에 남는다).
 *
 * 관리자용이라 문구는 한국어 고정이다 — 관리자 화면 전체가 운영자용 한국어다.
 *
 * 큐에 넣지 않고 그 자리에서 보낸다. 긴급 요청이라 늦게 가는 것은 안 간 것과 같다.
 */
class SosAlertedNotification extends Notification implements PersonalDataFreeChannel
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['fcm'];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>, urgent: bool}
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'data' => ['screen' => 'sos'],
            // 잠금화면을 깨워야 한다. 배터리 절약 모드에서도 즉시 전달된다.
            'urgent' => true,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function outboundStrings(): array
    {
        return [$this->title(), $this->body()];
    }

    private function title(): string
    {
        return '긴급 SOS 접수';
    }

    private function body(): string
    {
        return '즉시 확인이 필요합니다. 앱에서 상황판을 열어 주세요.';
    }
}
