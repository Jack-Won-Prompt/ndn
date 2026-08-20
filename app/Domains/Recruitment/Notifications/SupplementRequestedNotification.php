<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Notifications;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 가입 서류 보완 요청 메일 (업무흐름 §2).
 *
 * 승인 전 근로자는 앱에 로그인할 수 없다. 푸시도 기기 토큰이 등록돼 있어야 가는데
 * 웹으로 가입한 사람은 앱을 아직 깔지 않았을 수 있다. 그래서 **이메일이 유일하게
 * 확실한 통로**다.
 *
 * 본문에는 개인정보를 넣지 않는다(§7-3). 이름도 부르지 않고 부족한 항목 **개수**와
 * 서명 링크만 보낸다. 무엇이 부족한지는 링크를 열어야 보인다 — 메일은 다른 사람이
 * 대신 열어 볼 수 있는 통로다.
 *
 * 문구는 근로자 언어로 나간다(§6).
 */
class SupplementRequestedNotification extends Notification implements PersonalDataFreeChannel, ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $supplementUrl,
        public int $count,
        public int $expiresInDays,
        public string $workerLocale = 'ko',
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->line($this->intro())
            ->action($this->actionLabel(), $this->supplementUrl)
            ->line($this->outro());
    }

    /** @return array<int, string> */
    public function outboundStrings(): array
    {
        return [$this->subject(), $this->intro(), $this->actionLabel(), $this->outro()];
    }

    private function subject(): string
    {
        return __('worker.supplement_subject', [], $this->workerLocale);
    }

    private function intro(): string
    {
        return __('worker.supplement_intro', ['count' => $this->count], $this->workerLocale);
    }

    private function actionLabel(): string
    {
        return __('worker.supplement_action', [], $this->workerLocale);
    }

    private function outro(): string
    {
        return __('worker.supplement_outro', ['days' => $this->expiresInDays], $this->workerLocale);
    }
}
