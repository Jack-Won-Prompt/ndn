<?php

declare(strict_types=1);

namespace App\Domains\Support\Http\Controllers\Api;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Services\ChatService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 근로자 앱 채팅 API (CLAUDE.md §9). 근로자는 자국어(locale)로 작성·열람하며,
 * 조직(NDN·시청·농가) 상대 메시지는 자동으로 자국어로 번역되어 보인다.
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

        return response()->json(['data' => $this->chat->messagesFor($conversation, $me)]);
    }

    public function send(Request $request, ChatConversation $conversation): JsonResponse
    {
        $me = $this->me($request);
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->chat->send($conversation, $me, $data['body']);

        return response()->json(['data' => $this->chat->messagesFor($conversation, $me)]);
    }
}
