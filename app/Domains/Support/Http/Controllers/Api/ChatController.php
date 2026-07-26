<?php

declare(strict_types=1);

namespace App\Domains\Support\Http\Controllers\Api;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Models\ChatMessage;
use App\Domains\Support\Services\ChatService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 근로자 앱 채팅 API (CLAUDE.md §9). 근로자는 자국어(locale)로 작성·열람하며,
 * 조직(NDN·시청·농가) 상대 메시지는 자동으로 자국어로 번역되어 보인다.
 * supportworks 이식: 첨부·답장·수정·삭제·읽음표시.
 */
class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    private function me(Request $request): array
    {
        /** @var Worker $worker */
        $worker = $request->user();

        return $this->chat->partyForWorker($worker);
    }

    public function conversations(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->chat->conversationsFor($this->me($request))]);
    }

    /** 조직 상대와 대화 열기 (근로자는 ndn/city/farm 과 대화) */
    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:ndn,city,farm'],
            'id' => ['nullable', 'integer'],
        ]);
        $other = $data['type'] === 'ndn'
            ? ['ndn', null, 'ko']
            : [$data['type'], $data['id'], 'ko'];

        $conv = $this->chat->resolveConversation($this->me($request), $other);

        return response()->json(['id' => $conv->id]);
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $me = $this->me($request);
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);

        return response()->json(['data' => $this->messagesPayload($conversation, $me)]);
    }

    public function send(Request $request, ChatConversation $conversation): JsonResponse
    {
        $me = $this->me($request);
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,hwp,txt,zip'],
            'reply_to_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->chat->send(
                $conversation, $me,
                $data['body'] ?? null,
                $request->file('file'),
                isset($data['reply_to_id']) ? (int) $data['reply_to_id'] : null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->messagesPayload($conversation, $me)]);
    }

    /** 메시지 수정 (본인) */
    public function update(Request $request, ChatConversation $conversation, ChatMessage $message): JsonResponse
    {
        $me = $this->me($request);
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);
        abort_if($message->conversation_id !== $conversation->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        try {
            $this->chat->editMessage($conversation, $me, $message, $data['body']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->messagesPayload($conversation, $me)]);
    }

    /** 메시지 삭제 (본인) */
    public function destroy(Request $request, ChatConversation $conversation, ChatMessage $message): JsonResponse
    {
        $me = $this->me($request);
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);
        abort_if($message->conversation_id !== $conversation->id, 404);

        try {
            $this->chat->deleteMessage($conversation, $me, $message);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->messagesPayload($conversation, $me)]);
    }

    /** 첨부 다운로드 (참여자 근로자만) */
    public function file(Request $request, ChatConversation $conversation, ChatMessage $message): StreamedResponse
    {
        $me = $this->me($request);
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);

        return $this->chat->streamFile($conversation, $me, $message);
    }

    private function messagesPayload(ChatConversation $conversation, array $me): array
    {
        return $this->chat->messagesFor(
            $conversation, $me,
            fn (ChatMessage $m) => url("/api/v1/chat/{$conversation->id}/files/{$m->id}"),
        );
    }
}
