<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\User;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 관리자 앱 — 인증 (CLAUDE.md §9).
 *
 * 근로자 로그인(/auth/login)과 완전히 분리된 경로다. 발급되는 토큰의 소유자가
 * User 라서 `worker` 미들웨어를 통과하지 못하고, 반대로 근로자 토큰은
 * `portal` 미들웨어를 통과하지 못한다.
 *
 * 2FA(§2)는 웹 포털에서 처리한다. 앱은 2FA 필수 역할(ndn_admin)도 받아들이되,
 * 토큰 수명을 짧게 가져가는 대신 로그아웃으로 즉시 폐기할 수 있게 한다.
 */
class AdminAuthController extends Controller
{
    public function login(AdminLoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::where('email', $request->string('email')->value())->first();

        // 계정 존재 여부가 새어나가지 않도록 동일한 오류로 처리한다.
        if ($user === null || ! Hash::check($request->string('password')->value(), $user->password)) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }

        if (! $user->canUsePortalApp()) {
            throw ValidationException::withMessages([
                'email' => ['이 계정은 관리자 앱을 사용할 수 없습니다.'],
            ]);
        }

        $device = $request->input('device_name') ?: 'admin-app';
        $user->tokens()->where('name', $device)->delete();

        $role = $user->primaryPortalRole();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role?->value,
                'role_label' => $role?->label(),
            ],
            'meta' => [
                'token' => $user->createToken($device)->plainTextToken,
                // 앱이 역할별로 화면을 가르는 데 쓴다. 서버 스코프가 최종 방어선이다.
                'can_decide' => PortalScope::canDecide($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $role = $user->primaryPortalRole();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role?->value,
                'role_label' => $role?->label(),
            ],
            'meta' => ['can_decide' => PortalScope::canDecide($user)],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        return response()->json(['data' => ['message' => 'ok']]);
    }
}
