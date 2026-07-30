<?php

declare(strict_types=1);

namespace App\Domains\Site\Http\Controllers\Api;

use App\Domains\Support\Events\AdminAlertBroadcast;
use App\Domains\Support\Models\ChatVisitor;
use App\Domains\Support\Services\ChatService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 앱에서 보내는 문의 (로그인 전).
 *
 * 홈페이지의 문의는 방문자 채팅으로 들어가 관리자 콘솔의 '문의' 화면에 쌓인다.
 * 앱 문의도 **같은 곳으로** 보낸다 — 창구를 나누면 담당자가 두 곳을 봐야 한다.
 *
 * 웹은 쿠키로 방문자를 이어 붙이지만 앱은 쿠키를 쓰지 않는다. 그래서 첫 문의의
 * 응답으로 방문자 토큰을 주고, 앱이 그것을 보관했다가 다음 문의에 실어 보낸다.
 * 그래야 한 사람의 문의가 하나의 대화로 이어진다.
 *
 * 인증 밖이다 — 계정이 없는 농가·지자체가 도입을 문의하는 창구다.
 */
class SiteInquiryController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:60'],
            'contact' => ['nullable', 'string', 'max:120'],
            'locale' => ['nullable', 'string', 'max:8'],
            // 이어지는 문의라면 앞서 받은 토큰
            'visitor_token' => ['nullable', 'string', 'size:48'],
        ]);

        $locale = $data['locale'] ?? 'ko';
        $visitor = $this->visitor($data, $locale);

        // 연락처는 본문 첫 줄에 붙인다. 담당자가 답할 방법이 있어야 한다.
        $body = $data['body'];
        if (filled($data['contact'] ?? null)) {
            $body = '['.$data['contact'].'] '.$body;
        }

        $me = $this->chat->partyForVisitor($visitor);
        $conversation = $this->chat->resolveConversation($me, ['ndn', null, 'ko']);

        try {
            $this->chat->send($conversation, $me, $body);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // 관리자에게 실시간 안내 — 개인정보 없이 도착 사실만(§7-3). 실패해도 무시.
        try {
            broadcast(new AdminAlertBroadcast(
                'inquiry', '새 문의가 도착했습니다.', 'inquiries',
            ));
        } catch (\Throwable $e) {
            // 브로드캐스트 미설정이면 관리자 화면 폴링으로 확인된다
        }

        return response()->json([
            'data' => ['sent' => true],
            // 다음 문의를 같은 대화로 잇기 위해 앱이 보관한다.
            'meta' => ['visitor_token' => $visitor->token],
        ], 201);
    }

    private function visitor(array $data, string $locale): ChatVisitor
    {
        $existing = filled($data['visitor_token'] ?? null)
            ? ChatVisitor::where('token', $data['visitor_token'])->first()
            : null;

        if ($existing !== null) {
            $existing->forceFill([
                'locale' => $locale,
                'last_seen_at' => now(),
                'name' => $existing->name ?: ($data['name'] ?? null),
            ])->save();

            return $existing;
        }

        return ChatVisitor::create([
            'token' => Str::random(48),
            'name' => $data['name'] ?? null,
            'locale' => $locale,
            'first_page' => 'app',
            'last_seen_at' => now(),
        ]);
    }
}
