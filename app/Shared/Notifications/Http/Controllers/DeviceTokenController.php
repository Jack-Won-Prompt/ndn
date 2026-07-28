<?php

declare(strict_types=1);

namespace App\Shared\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Notifications\Actions\RegisterDeviceTokenAction;
use App\Shared\Notifications\Models\DeviceToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 푸시 수신 기기 등록·해제.
 *
 * 근로자·관리자가 같은 앱을 쓰므로 컨트롤러 하나가 두 라우트를 겸한다.
 * 소유자는 URL 이 아니라 **인증된 사용자에서 파생**한다(§9) — 남의 기기에
 * 자기 토큰을 붙이거나 그 반대를 할 수 없다.
 */
class DeviceTokenController extends Controller
{
    /** 등록·갱신 (앱 시작 시, 토큰 갱신 시). */
    public function store(Request $request, RegisterDeviceTokenAction $action): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'locale' => ['nullable', 'string', 'max:8'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'platform' => ['nullable', 'in:android,ios'],
        ]);

        /** @var Model $owner */
        $owner = $request->user();

        $device = $action->execute(
            owner: $owner,
            token: $data['token'],
            locale: $data['locale'] ?? 'ko',
            appVersion: $data['app_version'] ?? null,
            platform: $data['platform'] ?? 'android',
        );

        return response()->json([
            'data' => [
                'registered' => true,
                // 토큰 원문은 되돌려주지 않는다 — 로그·프록시에 남을 이유가 없다.
                'id' => $device->id,
            ],
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * 해제 (로그아웃 시).
     *
     * 로그아웃하면서 토큰을 지우지 않으면, 그 기기가 다음 사용자에게 넘어가도
     * 이전 사용자의 알림이 계속 간다.
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        /** @var Model $owner */
        $owner = $request->user();

        // 본인 소유일 때만 지운다 — 토큰 문자열만 알면 남의 등록을 지울 수 있으면 안 된다.
        $deleted = DeviceToken::query()
            ->where('token', $data['token'])
            ->where('tokenable_type', $owner->getMorphClass())
            ->where('tokenable_id', $owner->getKey())
            ->delete();

        return response()->json(['data' => ['deleted' => $deleted > 0]]);
    }
}
