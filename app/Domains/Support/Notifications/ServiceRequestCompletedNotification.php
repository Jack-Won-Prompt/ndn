<?php

declare(strict_types=1);

namespace App\Domains\Support\Notifications;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SR 적용 완료 알림 이메일 — 등록자에게 발송 (CLAUDE.md §7-3, §8).
 *
 * 외부(이메일)로 나가므로 PersonalDataFreeChannel 을 구현한다.
 * SR 제목·본문은 담당자가 자유 입력한 텍스트라 근로자 개인정보가 섞일 수 있으므로
 * 메일에 싣지 않는다. 식별에 필요한 SR 번호와 콘솔 링크만 담는다(§7-3: 건수 + 링크).
 */
class ServiceRequestCompletedNotification extends Notification implements PersonalDataFreeChannel, ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $serviceRequestId,
        public string $consoleUrl,
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
            ->action($this->actionLabel(), $this->consoleUrl)
            ->line($this->outro());
    }

    /**
     * 외부로 나가는 텍스트 — SR 번호와 안내뿐, 개인정보 없음.
     *
     * @return array<int, string>
     */
    public function outboundStrings(): array
    {
        return [$this->subject(), $this->intro(), $this->actionLabel(), $this->outro()];
    }

    private function subject(): string
    {
        return "[N.D.N 콘솔] SR #{$this->serviceRequestId} 적용 완료";
    }

    private function intro(): string
    {
        return "요청하신 SR #{$this->serviceRequestId} 이(가) 적용 완료로 처리되었습니다.";
    }

    private function actionLabel(): string
    {
        return '콘솔에서 처리 내역 확인';
    }

    private function outro(): string
    {
        return '처리 내용과 담당자 답글은 로그인 후 콘솔의 SR 화면에서 확인할 수 있습니다.';
    }
}
