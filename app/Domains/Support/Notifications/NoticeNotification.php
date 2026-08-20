<?php

declare(strict_types=1);

namespace App\Domains\Support\Notifications;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 근로자 공지사항 푸시/인앱 알림 (CLAUDE.md §6, §7-3, §8).
 *
 * 관리자가 작성한 공지를 **수신자 언어로 번역한 title/body** 를 받아 발송한다.
 * (일반 근로자 알림은 번역 키 기반이지만, 공지는 자유 텍스트라 발송 측에서 번역해 넣는다.)
 * §7-3: 개인정보가 없어야 하는 외부 채널이므로 PersonalDataFreeChannel 을 구현한다.
 * 개인정보 혼입은 발송 전 SendNoticeAction 이 패턴 검증으로 차단한다.
 *
 * 큐에 넣지 않고 그 자리에서 보낸다.
 *
 * ※ 대상이 많으면 사람 수만큼 FCM 호출이 이어져 [공지사항] 발송 요청이 길어진다.
 *   근로자가 수백 명이 되면 이 알림만 다시 큐로 돌릴 것.
 */
class NoticeNotification extends Notification implements PersonalDataFreeChannel
{
    use Queueable;

    public function __construct(
        public readonly int $noticeId,
        public readonly string $noticeTitle,
        public readonly string $noticeBody,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // 인앱 알림함(database) + 기기 푸시(fcm). 푸시를 놓쳐도 앱에서 볼 수 있게.
        return ['database', 'fcm'];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>, urgent: bool}
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->noticeTitle,
            'body' => $this->noticeBody,
            'data' => ['screen' => 'notices', 'notice_id' => (string) $this->noticeId],
            'urgent' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'notice',
            'notice_id' => $this->noticeId,
            'title' => $this->noticeTitle,
            'body' => $this->noticeBody,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function outboundStrings(): array
    {
        return [$this->noticeTitle, $this->noticeBody];
    }
}
