<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Support\Events\AdminAlertBroadcast;
use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Models\ChatVisitor;
use App\Domains\Support\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 회사소개 사이트 우하단 "문의하기" 실시간 채팅(방문자 ↔ NDN 관리자).
 *
 * 방문자는 로그인하지 않으므로 httpOnly 쿠키(ndn_visitor)의 토큰으로만 식별한다.
 * 대화는 기존 chat_conversations 에 저장되어 NDN 관리자는 콘솔 [채팅] 화면에서 응대한다.
 * 실시간성은 짧은 폴링(poll)으로 구현 — 별도 웹소켓 인프라(Reverb/Pusher) 불필요.
 *
 * §7-3: 이 채팅은 앱 내부 저장이며 외부 알림(알림톡/SMS)을 발송하지 않는다(개인정보 유출 없음).
 */
class SiteChatController extends Controller
{
    private const COOKIE = 'ndn_visitor';

    private const COOKIE_DAYS = 60 * 24 * 365;   // 1년(분 단위)

    public function __construct(private ChatService $chat) {}

    /** 방문자 메시지 전송 (최초 전송 시 방문자·대화 생성 + 쿠키 발급) */
    public function message(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        $locale = (string) $request->session()->get('site_locale', 'ko');
        $visitor = $this->currentVisitor($request);
        $newToken = null;

        if ($visitor === null) {
            $newToken = Str::random(48);
            $visitor = ChatVisitor::create([
                'token' => $newToken,
                'name' => $data['name'] ?? null,
                'locale' => $locale,
                'first_page' => Str::limit((string) $request->headers->get('referer'), 250, ''),
                'last_seen_at' => now(),
            ]);
        } else {
            $visitor->forceFill([
                'locale' => $locale,
                'last_seen_at' => now(),
                'name' => $visitor->name ?: ($data['name'] ?? null),
            ])->save();
        }

        $me = $this->chat->partyForVisitor($visitor);
        $conv = $this->chat->resolveConversation($me, ['ndn', null, 'ko']);

        try {
            $this->chat->send($conv, $me, $data['body']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        // 관리자 콘솔 실시간 알림 (개인정보 없이 건수 안내만, §7-3). 실패해도 무시.
        try {
            broadcast(new AdminAlertBroadcast(
                'inquiry', '새 홈페이지 문의가 도착했습니다.', 'inquiries',
            ));
        } catch (\Throwable $e) {
            // Pusher 미설정/실패 시 폴링으로 폴백
        }

        $response = response()->json([
            'ok' => true,
            'messages' => $this->chat->messagesFor($conv, $me),
        ]);

        if ($newToken !== null) {
            $response->withCookie(cookie(
                self::COOKIE, $newToken, self::COOKIE_DAYS,
                null, null, $request->isSecure(), true, false, 'Lax',
            ));
        }

        return $response;
    }

    /** 방문자 폴링 — 새 메시지(관리자 답장 포함)를 가져오고 읽음 처리 */
    public function poll(Request $request): JsonResponse
    {
        $visitor = $this->currentVisitor($request);
        if ($visitor === null) {
            return response()->json(['ok' => true, 'messages' => []]);
        }

        $me = $this->chat->partyForVisitor($visitor);
        // 방문자는 항상 대화의 a 측(자신이 먼저 개설)에 위치한다.
        $conv = ChatConversation::where('a_type', 'visitor')->where('a_id', $visitor->id)->first();
        if ($conv === null) {
            return response()->json(['ok' => true, 'messages' => []]);
        }

        $visitor->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'ok' => true,
            'messages' => $this->chat->messagesFor($conv, $me),
        ]);
    }

    private function currentVisitor(Request $request): ?ChatVisitor
    {
        $token = (string) $request->cookie(self::COOKIE);

        return $token !== '' ? ChatVisitor::where('token', $token)->first() : null;
    }
}
