<?php

declare(strict_types=1);

namespace App\Domains\Support\Services;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Events\ChatMessageSent;
use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Models\ChatMessage;
use App\Domains\Support\Models\ChatVisitor;
use App\Models\User;
use App\Shared\Support\LocalTime;
use App\Shared\Translation\GoogleTranslator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 채팅 공용 서비스 — 콘솔(NDN)·포털(시청·농가·해외협력사)·근로자 API 공용.
 *
 * 참여자(party) = [type, id, lang].
 *   type: ndn|city|farm|worker|agency|partner   (ndn 은 조직단위 id=null)
 *   근로자는 자국어(locale), 조직은 한국어(ko).
 *
 * supportworks 메시지 기능 이식: 첨부파일·답장·수정·삭제·읽음표시 + 자동번역.
 */
class ChatService
{
    /** 첨부 파일 저장 디스크 (private) */
    private const FILE_DISK = 'local';

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
        'visitor' => '홈페이지 방문자',
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

    /** 홈페이지 익명 방문자 → party (자국어는 사이트 선택 언어) */
    public function partyForVisitor(ChatVisitor $visitor): array
    {
        return ['visitor', $visitor->id, $visitor->locale ?: 'ko'];
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

    /**
     * 메시지 전송 (내 언어 → 상대 언어 자동번역 저장). 첨부·답장 지원.
     */
    public function send(
        ChatConversation $conv,
        array $me,
        ?string $body = null,
        ?UploadedFile $file = null,
        ?int $replyToId = null,
    ): ChatMessage {
        [$mt, $mi, $ml] = $me;
        $side = $this->sideOrFail($conv, $me);
        $otherSide = $conv->otherSide($side);
        $otherLang = $conv->langForSide($otherSide);

        $body = $body !== null ? trim($body) : null;
        if (($body === null || $body === '') && $file === null) {
            throw new \RuntimeException('메시지 또는 첨부가 필요합니다.');
        }

        // 첨부 저장 (private 디스크)
        $filePath = $fileName = $fileMime = null;
        $fileSize = null;
        if ($file !== null) {
            $filePath = $file->store("chat/{$conv->id}", self::FILE_DISK);
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileMime = $file->getMimeType();
        }

        // 본문 자동번역 (파일만 있으면 번역 없음).
        // from 은 'auto' — 발신자의 선언 언어($ml)가 부정확해도 실제 언어를 감지해 상대 언어로 번역.
        // (예: 방글라 방문자가 언어를 안 바꿔도 관리자는 한국어 번역본을 받는다.)
        $translated = null;
        if ($body !== null && $body !== '' && $otherLang !== $ml) {
            $translated = GoogleTranslator::translate($body, $otherLang, 'auto');
            // 번역 결과가 원문과 사실상 동일하면(이미 상대 언어였음) 번역본 미저장
            if ($translated !== null && trim($translated) === trim($body)) {
                $translated = null;
            }
        }

        // 답장 대상은 같은 대화의 메시지여야 함
        $replyId = null;
        if ($replyToId !== null) {
            $replyId = $conv->messages()->whereKey($replyToId)->value('id');
        }

        $msg = $conv->messages()->create([
            'sender_side' => $side,
            'body' => $body,
            'body_lang' => $ml,
            'translated_body' => $translated,
            'translate_lang' => $translated !== null ? $otherLang : null,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'file_mime' => $fileMime,
            'reply_to_id' => $replyId,
        ]);

        $conv->forceFill([
            'last_message_at' => $msg->created_at,
            "{$side}_last_read_at" => $msg->created_at,
        ])->save();

        $this->broadcast($conv);

        return $msg;
    }

    /** 내 메시지 수정 (재번역). 본인 메시지만, 삭제된 것은 불가. */
    public function editMessage(ChatConversation $conv, array $me, ChatMessage $msg, string $body): ChatMessage
    {
        [, , $ml] = $me;
        $side = $this->sideOrFail($conv, $me);
        $this->assertOwnEditable($msg, $side);

        $body = trim($body);
        if ($body === '') {
            throw new \RuntimeException('내용을 입력하세요.');
        }

        $otherLang = $conv->langForSide($conv->otherSide($side));
        $translated = $otherLang !== $ml ? GoogleTranslator::translate($body, $otherLang, $ml) : null;

        $msg->forceFill([
            'body' => $body,
            'body_lang' => $ml,
            'translated_body' => $translated,
            'translate_lang' => $translated !== null ? $otherLang : null,
            'edited_at' => now(),
        ])->save();

        $this->broadcast($conv);

        return $msg;
    }

    /** 내 메시지 삭제 (소프트 표시 — 행은 유지, 본문/첨부 파기). */
    public function deleteMessage(ChatConversation $conv, array $me, ChatMessage $msg): void
    {
        $side = $this->sideOrFail($conv, $me);
        $this->assertOwnEditable($msg, $side);

        // 첨부 실제 파일 파기
        if ($msg->hasFile() && Storage::disk(self::FILE_DISK)->exists($msg->file_path)) {
            Storage::disk(self::FILE_DISK)->delete($msg->file_path);
        }

        $msg->forceFill([
            'body' => null,
            'translated_body' => null,
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
            'file_mime' => null,
            'deleted_at' => now(),
        ])->save();

        $this->broadcast($conv);
    }

    /** 첨부 파일 스트리밍 (참여자만). */
    public function streamFile(ChatConversation $conv, array $me, ChatMessage $msg)
    {
        $this->sideOrFail($conv, $me);
        abort_unless(
            $msg->conversation_id === $conv->id && $msg->hasFile()
                && Storage::disk(self::FILE_DISK)->exists($msg->file_path),
            404,
        );

        $disposition = $msg->isImage() ? 'inline' : 'attachment';

        return Storage::disk(self::FILE_DISK)->response(
            $msg->file_path,
            $msg->file_name,
            ['Content-Type' => $msg->file_mime ?: 'application/octet-stream'],
            $disposition,
        );
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
            ->whereNull('deleted_at')
            ->when($lastReadAt, fn ($q) => $q->where('created_at', '>', $lastReadAt))
            ->count();

        return [
            'id' => $c->id,
            'other_type' => $otherType,
            'title' => $this->conversationTitle($c, $otherSide, $otherType),
            'last' => $last ? $last->previewFor($side) : null,
            'last_at' => LocalTime::format($c->last_message_at),
            'unread' => $unread,
        ];
    }

    private function conversationTitle(ChatConversation $c, string $otherSide, string $otherType): string
    {
        if ($otherType === 'worker' && $c->worker) {
            return $c->worker->name.' · 근로자';
        }
        if ($otherType === 'visitor') {
            $v = ChatVisitor::find($c->{$otherSide.'_id'});
            $who = $v && filled($v->name) ? $v->name : '방문자 #'.$c->{$otherSide.'_id'};

            return $who.' · 홈페이지 문의';
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

    /**
     * 대화의 메시지들을 뷰어 언어로 매핑하고 읽음 처리.
     * $fileUrl: (ChatMessage) => string  — 첨부 다운로드 URL 빌더(웹/API 상이).
     */
    public function messagesFor(ChatConversation $conv, array $me, ?callable $fileUrl = null): array
    {
        $side = $this->sideOrFail($conv, $me);
        $otherSide = $conv->otherSide($side);

        // 상대가 내 메시지를 어디까지 읽었는지 (읽음 영수증 계산 기준)
        $otherReadAt = $conv->{$otherSide.'_last_read_at'};

        $rows = $conv->messages()->with('replyTo')->get()->map(function (ChatMessage $m) use ($side, $otherReadAt, $fileUrl) {
            $mine = $m->sender_side === $side;
            $row = [
                'id' => $m->id,
                'mine' => $mine,
                'body' => $m->bodyForViewer($side),
                'deleted' => $m->isDeleted(),
                'translated' => ! $mine && ! $m->isDeleted() && $m->translated_body !== null,
                'edited' => $m->edited_at !== null && ! $m->isDeleted(),
                'at' => LocalTime::format($m->created_at),
            ];

            // 상대 메시지가 번역된 경우, 원어(발신자 원문)도 함께 전달 → 화면에서 번역본+원어 동시 표시
            if ($row['translated']) {
                $row['original'] = (string) $m->body;
                $row['original_lang'] = (string) $m->body_lang;
            }

            // 첨부
            if ($m->hasFile()) {
                $row['file'] = [
                    'name' => $m->file_name,
                    'size' => $m->file_size,
                    'is_image' => $m->isImage(),
                    'url' => $fileUrl ? $fileUrl($m) : null,
                ];
            }

            // 답장 대상 미리보기
            if ($m->replyTo) {
                $row['reply'] = [
                    'id' => $m->replyTo->id,
                    'mine' => $m->replyTo->sender_side === $side,
                    'preview' => $m->replyTo->previewFor($side),
                ];
            }

            // 읽음 영수증 (내 메시지에 한해, 상대가 읽었는지)
            if ($mine && ! $m->isDeleted()) {
                $row['read'] = $otherReadAt !== null && $otherReadAt->gte($m->created_at);
            }

            return $row;
        })->all();

        $this->markRead($conv, $side);

        return $rows;
    }

    /** 읽음 처리 — 실제로 새로 읽은 상대 메시지가 있으면 상대에게 읽음 갱신 브로드캐스트. */
    private function markRead(ChatConversation $conv, string $side): void
    {
        $otherSide = $conv->otherSide($side);
        $lastReadAt = $conv->{$side.'_last_read_at'};
        $hadUnread = $conv->messages()
            ->where('sender_side', $otherSide)
            ->whereNull('deleted_at')
            ->when($lastReadAt, fn ($q) => $q->where('created_at', '>', $lastReadAt))
            ->exists();

        $conv->forceFill([$side.'_last_read_at' => now()])->save();

        if ($hadUnread) {
            $this->broadcast($conv);   // 상대(발신자)의 읽음표시 갱신
        }
    }

    /** 대화 참여자 side 확인, 아니면 예외. */
    private function sideOrFail(ChatConversation $conv, array $me): string
    {
        $side = $conv->sideOf($me[0], $me[1]);
        if ($side === null) {
            throw new \RuntimeException('대화 참여자가 아닙니다.');
        }

        return $side;
    }

    /** 본인 메시지이며 수정/삭제 가능한지. */
    private function assertOwnEditable(ChatMessage $msg, string $side): void
    {
        if ($msg->sender_side !== $side) {
            throw new \RuntimeException('본인 메시지만 수정/삭제할 수 있습니다.');
        }
        if ($msg->isDeleted()) {
            throw new \RuntimeException('이미 삭제된 메시지입니다.');
        }
    }

    /** 실시간 알림 (Pusher). 실패해도 동작에는 영향 없음. */
    private function broadcast(ChatConversation $conv): void
    {
        try {
            broadcast(ChatMessageSent::forConversation($conv))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('[Chat] broadcast 실패: '.$e->getMessage());
        }
    }
}
