<?php

declare(strict_types=1);

namespace App\Domains\Support\Services;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Events\ChatMessageSent;
use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Models\ChatMessage;
use App\Models\User;
use App\Shared\Translation\GoogleTranslator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 채팅 공용 서비스 — 콘솔(NDN)·포털(시청·농가·해외협력사)·근로자 API 공용.
 *
 * 참여자(party) = [type, id, lang].
 *   type: ndn|city|farm|worker|agency|partner   (ndn 은 조직단위 id=null)
 *   근로자는 자국어(locale), 조직은 한국어(ko).
 */
class ChatService
{
    private const ROLE_TYPE = [
        'ndn_admin' => 'ndn',
        'city_officer' => 'city',
        'farm_owner' => 'farm',
        'sending_agency' => 'agency',
        'partner_agency' => 'partner',
    ];

    public const TYPE_LABEL = [
        'ndn' => 'NDN 관리자', 'city' => '시청', 'farm' => '농가',
        'worker' => '근로자', 'agency' => '해외 협력사', 'partner' => '제휴 대리점',
    ];

    /** 로그인 사용자 → party [type,id,lang] */
    public function partyForUser(User $user): array
    {
        $role = $user->getRoleNames()->first();
        $type = self::ROLE_TYPE[$role] ?? 'ndn';

        return [$type, $type === 'ndn' ? null : $user->id, 'ko'];
    }

    /** 근로자 → party */
    public function partyForWorker(Worker $worker): array
    {
        return ['worker', $worker->id, $worker->locale ?: 'ko'];
    }

    /** 두 참여자 사이 대화 조회/생성 */
    public function resolveConversation(array $me, array $other): ChatConversation
    {
        [$mt, $mi, $ml] = $me;
        [$ot, $oi, $ol] = $other;

        $match = function ($q, string $p, string $type, ?int $id) {
            $q->where("{$p}_type", $type);
            $type === 'ndn' ? $q->whereNull("{$p}_id") : $q->where("{$p}_id", $id);
        };

        $conv = ChatConversation::where(function ($q) use ($match, $mt, $mi, $ot, $oi) {
            $q->where(fn ($w) => $match($w, 'a', $mt, $mi))->where(fn ($w) => $match($w, 'b', $ot, $oi));
        })->orWhere(function ($q) use ($match, $mt, $mi, $ot, $oi) {
            $q->where(fn ($w) => $match($w, 'a', $ot, $oi))->where(fn ($w) => $match($w, 'b', $mt, $mi));
        })->first();

        if ($conv) {
            return $conv;
        }

        $kinds = [$mt, $ot];
        sort($kinds);

        return ChatConversation::create([
            'kind' => implode('_', $kinds),
            'a_type' => $mt, 'a_id' => $mi, 'a_lang' => $ml,
            'b_type' => $ot, 'b_id' => $oi, 'b_lang' => $ol,
            'worker_id' => $mt === 'worker' ? $mi : ($ot === 'worker' ? $oi : null),
        ]);
    }

    /** 메시지 전송 (내 언어 → 상대 언어 자동번역 저장) */
    public function send(ChatConversation $conv, array $me, string $body): ChatMessage
    {
        [$mt, $mi, $ml] = $me;
        $side = $conv->sideOf($mt, $mi);
        if ($side === null) {
            throw new \RuntimeException('대화 참여자가 아닙니다.');
        }
        $otherSide = $conv->otherSide($side);
        $otherLang = $conv->langForSide($otherSide);

        $translated = null;
        if ($otherLang !== $ml) {
            $translated = GoogleTranslator::translate($body, $otherLang, $ml);
        }

        $msg = $conv->messages()->create([
            'sender_side' => $side,
            'body' => $body,
            'body_lang' => $ml,
            'translated_body' => $translated,
            'translate_lang' => $translated !== null ? $otherLang : null,
        ]);

        $conv->forceFill([
            'last_message_at' => $msg->created_at,
            "{$side}_last_read_at" => $msg->created_at,
        ])->save();

        // 실시간 알림 (Pusher) — 두 참여자 채널로. 실패해도 전송은 성공.
        try {
            broadcast(ChatMessageSent::forConversation($conv))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('[Chat] broadcast 실패: '.$e->getMessage());
        }

        return $msg;
    }

    /** 내가 참여한 대화 목록 (최근순, 미읽음 수 포함) */
    public function conversationsFor(array $me): Collection
    {
        [$mt, $mi] = $me;

        return ChatConversation::query()
            ->where(function ($q) use ($mt, $mi) {
                $q->where(fn ($w) => $this->wherePartyIs($w, 'a', $mt, $mi))
                    ->orWhere(fn ($w) => $this->wherePartyIs($w, 'b', $mt, $mi));
            })
            ->with('worker')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ChatConversation $c) => $this->summarize($c, $me));
    }

    private function wherePartyIs($q, string $p, string $type, ?int $id): void
    {
        $q->where("{$p}_type", $type);
        $type === 'ndn' ? $q->whereNull("{$p}_id") : $q->where("{$p}_id", $id);
    }

    /** 대화 요약(목록 카드용) */
    public function summarize(ChatConversation $c, array $me): array
    {
        [$mt, $mi] = $me;
        $side = $c->sideOf($mt, $mi);
        $otherSide = $c->otherSide($side);
        $otherType = $c->typeForSide($otherSide);

        $last = $c->messages()->latest('id')->first();
        $lastReadAt = $c->{$side.'_last_read_at'};
        $unread = $c->messages()
            ->where('sender_side', $otherSide)
            ->when($lastReadAt, fn ($q) => $q->where('created_at', '>', $lastReadAt))
            ->count();

        return [
            'id' => $c->id,
            'other_type' => $otherType,
            'title' => $this->conversationTitle($c, $otherSide, $otherType),
            'last' => $last ? $last->bodyForViewer($side) : null,
            'last_at' => $c->last_message_at?->format('Y-m-d H:i'),
            'unread' => $unread,
        ];
    }

    private function conversationTitle(ChatConversation $c, string $otherSide, string $otherType): string
    {
        if ($otherType === 'worker' && $c->worker) {
            return $c->worker->name.' · 근로자';
        }
        $label = self::TYPE_LABEL[$otherType] ?? $otherType;
        $id = $c->{$otherSide.'_id'};
        if ($id && $otherType !== 'ndn') {
            $u = User::find($id);
            if ($u) {
                return $u->name.' · '.$label;
            }
        }

        return $label;
    }

    /** 대화의 메시지들을 뷰어 언어로 매핑하고 읽음 처리 */
    public function messagesFor(ChatConversation $conv, array $me): array
    {
        [$mt, $mi] = $me;
        $side = $conv->sideOf($mt, $mi);

        $rows = $conv->messages()->get()->map(fn (ChatMessage $m) => [
            'id' => $m->id,
            'mine' => $m->sender_side === $side,
            'body' => $m->bodyForViewer($side),
            'original' => $m->body,               // 원문(참고 표시용)
            'translated' => $m->sender_side !== $side && $m->translated_body !== null,
            'at' => $m->created_at->format('Y-m-d H:i'),
        ])->all();

        // 읽음 처리
        $conv->forceFill([$side.'_last_read_at' => now()])->save();

        return $rows;
    }
}
