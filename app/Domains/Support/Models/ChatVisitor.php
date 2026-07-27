<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 회사소개 사이트 익명 방문자. 쿠키 token 으로만 식별한다(로그인 없음).
 * ChatConversation 에서 a_type='visitor', a_id=이 모델 id 로 참여한다.
 */
class ChatVisitor extends Model
{
    protected $fillable = [
        'token', 'name', 'locale', 'first_page', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
