<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Notifications;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 제휴 대리점에 정착 서비스 건이 배정되었음을 알린다 (CLAUDE.md §7-3, §8, 업무흐름 §6-3).
 *
 * 외부 채널(알림톡/SMS)로도 나갈 수 있으므로 PersonalDataFreeChannel 을 구현한다.
 * 본문에는 근로자 이름·전화·주소 등 개인정보를 절대 넣지 않으며,
 * "새 배정 건 N건 + 포털 로그인 링크" 와 서비스 유형(통장/보험/통신/유심)만 담는다.
 * (서비스 유형은 개인 식별정보가 아니다.)
 *
 * 큐에 넣지 않고 그 자리에서 보낸다. 인앱 알림함에 넣는 것뿐이라 비용이 없고,
 * 큐가 멈춰 있으면 그것만 조용히 사라진다.
 */
class SettlementAssignedNotification extends Notification implements PersonalDataFreeChannel
{
    use Queueable;

    /**
     * @param  int  $count  이번에 배정된 건수
     * @param  string  $typeLabel  서비스 유형 라벨(통장/보험/통신/유심) — 개인정보 아님
     */
    public function __construct(
        public readonly int $count,
        public readonly string $typeLabel,
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
            'kind' => 'settlement_assigned',
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => route('portal.settlements.index'),
        ];
    }

    /**
     * 외부로 나가는 텍스트 — 건수·유형·로그인 안내뿐 (개인정보 없음, §7-3).
     *
     * @return array<int, string>
     */
    public function outboundStrings(): array
    {
        return [$this->title(), $this->body()];
    }

    private function title(): string
    {
        return '새 정착 서비스 배정';
    }

    private function body(): string
    {
        return "{$this->typeLabel} 서비스 배정 건이 {$this->count}건 도착했습니다. 포털에 로그인해 확인·처리해 주세요.";
    }
}
