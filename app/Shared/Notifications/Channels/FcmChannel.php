<?php

declare(strict_types=1);

namespace App\Shared\Notifications\Channels;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use App\Shared\Notifications\FcmSender;
use App\Shared\Notifications\Models\DeviceToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Laravel 알림을 FCM 푸시로 내보내는 채널.
 *
 * `via()` 에 'fcm' 을 넣으면 이 채널이 잡는다. 알림 클래스는 `toFcm()` 으로
 * 제목·본문·데이터를 준다.
 *
 * **푸시는 잠금화면에 그대로 뜨는 외부 채널이다.** 로그인 없이 남이 볼 수 있으므로
 * 개인정보를 실을 수 없다. 그래서 이 채널로 나가는 알림은 반드시
 * PersonalDataFreeChannel 을 구현해야 하며, 아니면 여기서 막는다
 * (실수로 이름·여권번호가 실린 알림이 배포되는 것을 코드 단계에서 차단).
 */
class FcmChannel
{
    public function __construct(private readonly FcmSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof PersonalDataFreeChannel) {
            throw new LogicException(sprintf(
                '%s 는 푸시(fcm)로 보낼 수 없습니다. 푸시는 잠금화면에 노출되므로 '
                .'PersonalDataFreeChannel 을 구현해 개인정보가 없음을 보장해야 합니다.',
                $notification::class,
            ));
        }

        if (! method_exists($notification, 'toFcm')) {
            throw new LogicException($notification::class.' 에 toFcm() 이 없습니다.');
        }

        $devices = $this->devicesFor($notifiable);
        if ($devices->isEmpty()) {
            return;
        }

        /** @var array{title: string, body: string, data?: array<string, string>, urgent?: bool} $message */
        $message = $notification->toFcm($notifiable);

        $this->sender->send(
            devices: $devices,
            title: $message['title'],
            body: $message['body'],
            data: $message['data'] ?? [],
            urgent: $message['urgent'] ?? false,
        );
    }

    /**
     * 이 수신자의 기기 목록.
     *
     * @return Collection<int, DeviceToken>
     */
    private function devicesFor(object $notifiable): Collection
    {
        if (! $notifiable instanceof Model) {
            return collect();
        }

        return DeviceToken::query()
            ->where('tokenable_type', $notifiable->getMorphClass())
            ->where('tokenable_id', $notifiable->getKey())
            ->get();
    }
}
