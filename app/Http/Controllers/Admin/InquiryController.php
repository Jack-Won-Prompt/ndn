<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Services\ChatService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 홈페이지 "문의하기" 관리 (채팅과 분리된 별도 콘솔 화면).
 *
 * 백엔드는 기존 채팅 시스템을 재사용하되, other_type='visitor' 대화만 다룬다.
 * (자동 번역·원어 병기·읽음 처리 그대로 활용). NDN 관리자는 party=[ndn] 로 접근한다.
 */
class InquiryController extends Controller
{
    public function __construct(private ChatService $chat) {}

    private function me(): array
    {
        return $this->chat->partyForUser(Auth::user());
    }

    /** 방문자 문의 대화 목록 (visitor 대화만) */
    public function conversations(): JsonResponse
    {
        $list = $this->chat->conversationsFor($this->me())
            ->filter(fn ($c) => $c['other_type'] === 'visitor')
            ->values();

        return response()->json(['conversations' => $list]);
    }

    /** 문의 대화 메시지 (읽음 처리 포함) */
    public function messages(ChatConversation $conversation): JsonResponse
    {
        $me = $this->me();
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);
        abort_unless($this->isVisitorConversation($conversation), 404);

        return response()->json(['messages' => $this->chat->messagesFor($conversation, $me)]);
    }

    /** 관리자 답변 전송 */
    public function send(Request $request, ChatConversation $conversation): JsonResponse
    {
        $me = $this->me();
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);
        abort_unless($this->isVisitorConversation($conversation), 404);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        try {
            $this->chat->send($conversation, $me, $data['body']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['messages' => $this->chat->messagesFor($conversation, $me)]);
    }

    private function isVisitorConversation(ChatConversation $conversation): bool
    {
        return $conversation->a_type === 'visitor' || $conversation->b_type === 'visitor';
    }
}
