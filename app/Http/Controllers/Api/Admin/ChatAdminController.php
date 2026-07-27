<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Services\ChatService;
use App\Http\Controllers\Api\Admin\Concerns\ScopesPortalQueries;
use App\Http\Controllers\Controller;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 관리자 앱 — 근로자와의 메시지 (업무흐름 §8).
 *
 * 근로자는 자국어로 쓰고 관리자는 한국어로 보며, 번역은 ChatService 가 처리한다.
 * 근로자 앱 채팅과 같은 서비스·같은 대화를 쓰되, 참여자(party)만 User 쪽이다.
 */
class ChatAdminController extends Controller
{
    use ScopesPortalQueries;

    public function __construct(private ChatService $chat) {}

    private function me(Request $request): array
    {
        return $this->chat->partyForUser($this->actor($request));
    }

    /** 내가 참여한 대화 목록 */
    public function conversations(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->chat->conversationsFor($this->me($request)),
        ]);
    }

    /** 근로자와 대화 열기 — 스코프 안의 근로자만 가능 */
    public function open(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate(['worker_id' => ['required', 'integer']]);

        abort_unless(
            PortalScope::canSeeWorker($actor, $data['worker_id']),
            404,
            '해당 근로자를 찾을 수 없습니다.',
        );

        $worker = Worker::findOrFail($data['worker_id']);

        $conversation = $this->chat->resolveConversation(
            $this->me($request),
            $this->chat->partyForWorker($worker),
        );

        $this->logWorkerAccess($actor, [$worker->id], 'chat-open');

        return response()->json(['data' => ['id' => $conversation->id]]);
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
