<?php

declare(strict_types=1);

use App\Domains\Support\Services\ChatService;
use Illuminate\Support\Facades\Broadcast;

/*
| 채팅 브로드캐스트 채널 — 참여자(party) 단위 private 채널.
| chat.party.{type}.{id}  (ndn 은 id=0). 로그인 사용자의 party 와 일치해야 구독 허용.
*/
Broadcast::channel('chat.party.{type}.{id}', function ($user, string $type, string $id) {
    [$mt, $mi] = app(ChatService::class)->partyForUser($user);

    return $mt === $type && (string) ($mi ?? 0) === $id;
});
