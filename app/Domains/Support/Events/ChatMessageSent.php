<?php

declare(strict_types=1);

namespace App\Domains\Support\Events;

use App\Domains\Support\Models\ChatConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 새 채팅 메시지 알림. 두 참여자의 party 채널로 브로드캐스트한다.
 * 페이로드는 대화 id 만 담고(언어별 렌더는 각 클라이언트가 재조회) 가볍게 유지.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $conversationId, public array $channels) {}

    public static function forConversation(ChatConversation $conv): self
    {
        return new self($conv->id, [
            'chat.party.'.$conv->a_type.'.'.($conv->a_id ?? 0),
            'chat.party.'.$conv->b_type.'.'.($conv->b_id ?? 0),
        ]);
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(fn (string $c) => new PrivateChannel($c), $this->channels);
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId];
    }
}
