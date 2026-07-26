<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 채팅 메시지. body=작성 원문, translated_body=상대 표시 언어로 자동번역.
 * 뷰어는 자기 메시지는 원문, 상대 메시지는 번역본으로 본다.
 */
class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_side', 'body', 'body_lang', 'translated_body', 'translate_lang',
    ];

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    /** 뷰어(자기 쪽 side)가 볼 본문: 자기 메시지는 원문, 상대 메시지는 번역본. */
    public function bodyForViewer(string $viewerSide): string
    {
        if ($this->sender_side === $viewerSide) {
            return $this->body;
        }

        return $this->translated_body ?: $this->body;
    }
}
