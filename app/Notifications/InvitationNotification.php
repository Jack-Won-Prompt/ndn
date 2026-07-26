<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 조직 초대 이메일 (CLAUDE.md §7-3, §8).
 *
 * 외부(이메일)로 나가므로 PersonalDataFreeChannel 을 구현한다. 본문에는 이름·연락처 등
 * 개인정보를 넣지 않고, 역할 라벨(조직 유형) + 계정 설정 링크만 담는다. 링크의 토큰은
 * 소문자 hex 라 개인정보 패턴과 겹치지 않는다.
 */
class InvitationNotification extends Notification implements PersonalDataFreeChannel, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $acceptUrl,
        public readonly string $roleLabel,
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
            ->action($this->actionLabel(), $this->acceptUrl)
            ->line($this->outro());
    }

    /**
     * 외부로 나가는 사람이 읽는 텍스트 — 개인정보 없음 (역할 라벨 + 안내뿐).
     *
     * @return array<int, string>
     */
    public function outboundStrings(): array
    {
        return [$this->subject(), $this->intro(), $this->actionLabel(), $this->outro()];
    }

    private function subject(): string
    {
        return 'N.D.N Korea 협력 포털 초대';
    }

    private function intro(): string
    {
        return "{$this->roleLabel} 계정으로 초대되었습니다. 아래 버튼에서 계정을 설정해 주세요.";
    }

    private function actionLabel(): string
    {
        return '계정 설정하기';
    }

    private function outro(): string
    {
        return '이 초대 링크는 만료 기한이 지나면 사용할 수 없습니다. 본인이 신청하지 않았다면 이 메일을 무시하세요.';
    }
}
