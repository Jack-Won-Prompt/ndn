<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Models\ChatMessage;
use App\Domains\Support\Services\ChatService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 조직 사용자(NDN·시청·농가·해외협력사)용 채팅 (웹 세션).
 * 근로자는 별도 API(Api\ChatController) 사용.
 */
class ChatController extends Controller
{
    /** 내 역할에서 대화 가능한 조직 상대 유형 */
    private const ALLOWED = [
        'ndn' => ['city', 'farm', 'agency', 'worker'],
        'city' => ['ndn', 'farm', 'worker'],
        'farm' => ['ndn', 'city', 'worker'],
        'agency' => ['ndn', 'worker'],
        'partner' => ['ndn'],
    ];

    public function __construct(private ChatService $chat) {}

    private function me(): array
    {
        return $this->chat->partyForUser(Auth::user());
    }

    /** 내 대화 목록 */
    public function conversations(): JsonResponse
    {
        return response()->json(['conversations' => $this->chat->conversationsFor($this->me())]);
    }

    /** 조직 상대 연락처 + (근로자는 검색) */
    public function contacts(): JsonResponse
    {
        [$myType] = $this->me();
        $allowed = self::ALLOWED[$myType] ?? [];
        $orgs = [];

        foreach (['city' => 'city_officer', 'farm' => 'farm_owner', 'agency' => 'sending_agency'] as $type => $role) {
            if (! in_array($type, $allowed, true)) {
                continue;
            }
            foreach (User::role($role)->orderBy('name')->get() as $u) {
                $orgs[] = ['type' => $type, 'id' => $u->id, 'name' => $u->name, 'label' => ChatService::TYPE_LABEL[$type]];
            }
        }
        if (in_array('ndn', $allowed, true)) {
            $orgs[] = ['type' => 'ndn', 'id' => null, 'name' => 'NDN 관리자', 'label' => 'NDN'];
        }

        return response()->json(['orgs' => $orgs, 'canSearchWorker' => in_array('worker', $allowed, true)]);
    }

    /** 근로자 검색 */
    public function searchWorkers(Request $request): JsonResponse
    {
        [$myType] = $this->me();
        abort_unless(in_array('worker', self::ALLOWED[$myType] ?? [], true), 403);

        $q = trim((string) $request->query('q', ''));
        $rows = Worker::query()
            ->when($q !== '', fn ($x) => $x->where('name', 'like', "%{$q}%"))
            ->orderBy('name')->limit(30)->get(['id', 'name', 'nationality', 'locale']);

        return response()->json(['workers' => $rows]);
    }

    /** 대화 열기(없으면 생성) */
    public function open(Request $request): JsonResponse
    {
        [$myType] = $me = $this->me();
        $data = $request->validate([
            'type' => ['required', 'in:ndn,city,farm,agency,partner,worker'],
            'id' => ['nullable', 'integer'],
        ]);
        abort_unless(in_array($data['type'], self::ALLOWED[$myType] ?? [], true), 403);

        if ($data['type'] === 'worker') {
            $w = Worker::findOrFail($data['id']);
            $other = $this->chat->partyForWorker($w);
        } elseif ($data['type'] === 'ndn') {
            $other = ['ndn', null, 'ko'];
        } else {
            $u = User::findOrFail($data['id']);
            $other = [$data['type'], $u->id, 'ko'];
        }

        $conv = $this->chat->resolveConversation($me, $other);

        return response()->json(['id' => $conv->id]);
    }

    /** 대화 메시지 */
    public function messages(ChatConversation $conversation): JsonResponse
    {
        $me = $this->me();
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);

        return response()->json(['messages' => $this->messagesPayload($conversation, $me)]);
    }

    /** 메시지 전송 (본문·첨부·답장) */
    public function send(Request $request, ChatConversation $conversation): JsonResponse
    {
        $me = $this->me();
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
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['messages' => $this->messagesPayload($conversation, $me)]);
    }

    /** 메시지 수정 (본인, 재번역) */
    public function update(Request $request, ChatConversation $conversation, ChatMessage $message): JsonResponse
    {
        $me = $this->me();
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);
        abort_if($message->conversation_id !== $conversation->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        try {
            $this->chat->editMessage($conversation, $me, $message, $data['body']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['messages' => $this->messagesPayload($conversation, $me)]);
    }

    /** 메시지 삭제 (본인) */
    public function destroy(ChatConversation $conversation, ChatMessage $message): JsonResponse
    {
        $me = $this->me();
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);
        abort_if($message->conversation_id !== $conversation->id, 404);

        try {
            $this->chat->deleteMessage($conversation, $me, $message);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['messages' => $this->messagesPayload($conversation, $me)]);
    }

    /** 첨부 파일 다운로드/미리보기 (참여자만) */
    public function file(ChatConversation $conversation, ChatMessage $message): StreamedResponse
    {
        $me = $this->me();
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);

        return $this->chat->streamFile($conversation, $me, $message);
    }

    /** 첨부 URL 을 포함한 메시지 페이로드. */
    private function messagesPayload(ChatConversation $conversation, array $me): array
    {
        return $this->chat->messagesFor(
            $conversation, $me,
            fn (ChatMessage $m) => route('chat.file', ['conversation' => $conversation->id, 'message' => $m->id]),
        );
    }
}
