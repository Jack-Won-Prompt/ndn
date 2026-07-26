<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 채팅 메시지. body=작성 원문, translated_body=상대 표시 언어로 자동번역.
 * 뷰어는 자기 메시지는 원문, 상대 메시지는 번역본으로 본다.
 *
 * supportworks 이식 필드: file_*(첨부), reply_to_id(답장), edited_at(수정), deleted_at(삭제 표시).
 */
class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_side', 'body', 'body_lang', 'translated_body', 'translate_lang',
        'file_path', 'file_name', 'file_size', 'file_mime', 'reply_to_id', 'edited_at', 'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    /** @return BelongsTo<ChatMessage, $this> 답장 대상 원 메시지 */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_id');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    /** 첨부가 이미지인지 (인라인 미리보기 대상) */
    public function isImage(): bool
    {
        return $this->hasFile() && str_starts_with((string) $this->file_mime, 'image/');
    }

    /** 뷰어(자기 쪽 side)가 볼 본문: 자기 메시지는 원문, 상대 메시지는 번역본. */
    public function bodyForViewer(string $viewerSide): string
    {
        if ($this->isDeleted()) {
            return '삭제된 메시지입니다.';
        }
        if ($this->sender_side === $viewerSide) {
            return (string) $this->body;
        }

        return $this->translated_body ?: (string) $this->body;
    }

    /** 목록/답장 미리보기용 짧은 요약 (첨부는 파일명 표기). */
    public function previewFor(string $viewerSide): string
    {
        if ($this->isDeleted()) {
            return '삭제된 메시지';
        }
        $text = $this->bodyForViewer($viewerSide);
        if ($text !== '') {
            return $text;
        }
        if ($this->isImage()) {
            return '📷 사진';
        }
        if ($this->hasFile()) {
            return '📎 '.$this->file_name;
        }

        return '';
    }
}
