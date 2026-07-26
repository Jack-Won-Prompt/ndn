<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Services\ChatService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return response()->json(['messages' => $this->chat->messagesFor($conversation, $me)]);
    }

    /** 메시지 전송 */
    public function send(Request $request, ChatConversation $conversation): JsonResponse
    {
        $me = $this->me();
        abort_if($conversation->sideOf($me[0], $me[1]) === null, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->chat->send($conversation, $me, $data['body']);

        return response()->json(['messages' => $this->chat->messagesFor($conversation, $me)]);
    }
}
