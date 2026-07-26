<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Recruitment\Models\Worker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 2자 대화. 참여자 a/b 를 비정규화 저장. 유형: ndn|city|farm|worker|agency.
 * ndn 은 조직 단위(id=null, 어떤 ndn_admin 이든 접근). 그 외는 특정 사용자/근로자.
 */
class ChatConversation extends Model
{
    protected $fillable = [
        'kind', 'a_type', 'a_id', 'a_lang', 'b_type', 'b_id', 'b_lang',
        'worker_id', 'a_last_read_at', 'b_last_read_at', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'a_last_read_at' => 'datetime',
            'b_last_read_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->orderBy('id');
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** 참여자 (type,id) 가 이 대화의 어느 쪽인지 'a'|'b'|null. ndn 은 id 무시(조직). */
    public function sideOf(string $type, ?int $id): ?string
    {
        if (self::partyMatches($this->a_type, $this->a_id, $type, $id)) {
            return 'a';
        }
        if (self::partyMatches($this->b_type, $this->b_id, $type, $id)) {
            return 'b';
        }

        return null;
    }

    public static function partyMatches(string $t1, ?int $i1, string $t2, ?int $i2): bool
    {
        if ($t1 !== $t2) {
            return false;
        }

        return $t1 === 'ndn' ? true : $i1 === $i2;   // ndn 은 조직 단위
    }

    public function langForSide(string $side): string
    {
        return $side === 'a' ? $this->a_lang : $this->b_lang;
    }

    public function otherSide(string $side): string
    {
        return $side === 'a' ? 'b' : 'a';
    }

    public function typeForSide(string $side): string
    {
        return $side === 'a' ? $this->a_type : $this->b_type;
    }
}
