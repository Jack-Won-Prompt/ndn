<?php

declare(strict_types=1);

namespace App\Domains\Support\Notifications;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * 근로자에게 "새 공지 N건" 을 알리는 알림 (CLAUDE.md §7-3, §8).
 *
 * 외부 채널(알림톡/SMS)로 나가므로 PersonalDataFreeChannel 을 구현한다.
 * 본문에는 이름·여권번호·전화번호·주소를 넣지 않으며, 건수 + 로그인 안내만 담는다.
 */
class NewNoticeNotification extends Notification implements PersonalDataFreeChannel, ShouldQueue
{
    use Queueable;

    // 부모 Notification 에 이미 $locale 프로퍼티가 있어 충돌하므로 별도 이름을 쓴다.
    public function __construct(
        public readonly int $count,
        public readonly string $workerLocale = 'ko',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
        ];
    }

    /**
     * 외부로 나가는 텍스트 — 건수와 로그인 안내뿐 (개인정보 없음).
     *
     * @return array<int, string>
     */
    public function outboundStrings(): array
    {
        return [$this->title(), $this->body()];
    }

    private function title(): string
    {
        return __('worker.greeting', [], $this->workerLocale);
    }

    private function body(): string
    {
        return __('worker.new_notice', ['count' => $this->count], $this->workerLocale)
            .' '.__('worker.login_to_view', [], $this->workerLocale);
    }
}
