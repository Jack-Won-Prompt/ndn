<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Notifications;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 근로자 비밀번호 재설정 메일.
 *
 * Laravel 기본 ResetPassword 알림을 그대로 쓰면 `password.reset`(관리자 Fortify)
 * 주소로 링크가 나가 근로자가 열 수 없는 화면으로 간다. 그래서 따로 만든다.
 *
 * 본문에 개인정보를 넣지 않는다(§7-3). 이름도 부르지 않고 링크와 유효시간만
 * 담는다 — 메일 주소는 다른 사람과 함께 쓰는 경우가 있다. 문구는 근로자 언어로
 * 나간다(§6).
 *
 * 큐에 넣지 않고 그 자리에서 보낸다. 메일은 받는 사람이 그것으로만 다음 행동을
 * 할 수 있는 통로라(보완 제출·비밀번호 재설정·초대 수락), 큐가 멈춰 있으면
 * 보낸 줄 알고 기다리게 된다. 발송 실패가 요청 자리에서 드러나는 편이 낫다.
 */
class WorkerResetPasswordNotification extends Notification implements PersonalDataFreeChannel
{
    use Queueable;

    public function __construct(
        public string $resetUrl,
        public int $expiresInMinutes,
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
            ->action($this->actionLabel(), $this->resetUrl)
            ->line($this->outro());
    }

    /** @return array<int, string> */
    public function outboundStrings(): array
    {
        return [$this->subject(), $this->intro(), $this->actionLabel(), $this->outro()];
    }

    private function subject(): string
    {
        return __('worker.reset_subject', [], $this->workerLocale);
    }

    private function intro(): string
    {
        return __('worker.reset_intro', [], $this->workerLocale);
    }

    private function actionLabel(): string
    {
        return __('worker.reset_action', [], $this->workerLocale);
    }

    private function outro(): string
    {
        return __('worker.reset_outro', ['minutes' => $this->expiresInMinutes], $this->workerLocale);
    }
}
