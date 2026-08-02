<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * 근로자에게 보내는 푸시 알림의 공통 뼈대.
 *
 * 푸시는 **잠금화면에 그대로 뜨는 외부 채널**이다. 옆 사람이 볼 수 있으므로
 * 본문에 이름·여권번호·전화번호·주소를 넣지 않는다(§7-3). 여기서는 번역 키만
 * 받고, 문구는 lang/{locale}/worker.php 에서만 온다 — 코드에 한국어를 직접
 * 쓰면 다른 언어 사용자에게 한국어가 나간다.
 *
 * PersonalDataFreeChannel 구현이 강제 사항이다. FcmChannel 이 이 인터페이스가
 * 없는 알림을 아예 거부한다.
 */
abstract class WorkerPushNotification extends Notification implements PersonalDataFreeChannel, ShouldQueue
{
    use Queueable;

    /**
     * readonly 를 쓰지 않는다. 이 알림은 ShouldQueue 라 큐에서 역직렬화되는데,
     * SerializesModels 는 자식 클래스 스코프에서 리플렉션으로 값을 되돌린다.
     * PHP 8.2 는 부모에 선언된 readonly 속성을 자식 스코프에서 초기화하지 못해
     * "Cannot initialize readonly property" 로 잡을 자체가 실패한다(운영 런타임 8.2).
     *
     * @param  string  $workerLocale  근로자 언어(§6). 부모 Notification 의 $locale 과
     *                                이름이 겹쳐 별도 이름을 쓴다.
     */
    public function __construct(public string $workerLocale = 'ko') {}

    /** 알림 제목 번역 키 (worker.* ) */
    abstract protected function titleKey(): string;

    /** 알림 본문 번역 키 (worker.* ) */
    abstract protected function bodyKey(): string;

    /**
     * 앱이 알림을 탭했을 때 열 화면. 화면 이름만 넘기고 식별자는 넘기지 않는다
     * — 알림 데이터도 기기에 남으므로 최소한만 싣는다.
     *
     * @return array<string, string>
     */
    protected function route(): array
    {
        return [];
    }

    /** 잠금화면을 깨워야 하는 긴급 알림인지. */
    protected function isUrgent(): bool
    {
        return false;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // database 는 앱 안의 알림함, fcm 은 기기 푸시. 둘 다 남긴다 —
        // 푸시를 놓쳐도 앱을 열면 볼 수 있어야 한다.
        return ['database', 'fcm'];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>, urgent: bool}
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'data' => $this->route(),
            'urgent' => $this->isUrgent(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body()] + $this->route();
    }

    /**
     * @return array<int, string>
     */
    public function outboundStrings(): array
    {
        return [$this->title(), $this->body()];
    }

    protected function title(): string
    {
        return __($this->titleKey(), [], $this->workerLocale);
    }

    protected function body(): string
    {
        return __($this->bodyKey(), [], $this->workerLocale);
    }
}
