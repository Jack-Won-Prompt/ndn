<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\SendInvitationAction;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Shared\Enums\UserRole;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * 조직 초대 관리 (콘솔, ndn_admin).
 *
 * 초대 발송/재발송/철회. 토큰 평문은 저장하지 않으므로 링크는 발송·재발송 시점에만
 * 반환해 콘솔에서 복사할 수 있다.
 */
class InvitationController extends Controller
{
    /** 초대 목록 (상태 파생 포함). */
    public static function rows(): array
    {
        return Invitation::with('invitedBy')->latest('id')->limit(500)->get()
            ->map(fn (Invitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => UserRole::from($i->role)->label(),
                'status' => $i->status()->value,
                'status_label' => $i->status()->label(),
                'invited_by' => $i->invitedBy?->name ?? '—',
                'created' => LocalTime::format($i->created_at),
                'expires' => LocalTime::format($i->expires_at),
                'can_manage' => $i->isPending(),
            ])->all();
    }

    /** 초대할 수 있는 역할 옵션. */
    public static function roleOptions(): array
    {
        return array_map(
            fn (UserRole $r) => ['value' => $r->value, 'label' => $r->label()],
            SendInvitationAction::INVITABLE,
        );
    }

    public function send(Request $request, SendInvitationAction $action): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $r) => $r->value, SendInvitationAction::INVITABLE))],
            'name' => ['nullable', 'string', 'max:100'],
            'assigned_agency_id' => ['nullable', 'integer'],
        ]);

        try {
            $result = $action->execute(
                $data['email'],
                UserRole::from($data['role']),
                Auth::user(),
                $data['name'] ?? null,
                $data['assigned_agency_id'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json([
            'ok' => true,
            'url' => $result['url'],   // 콘솔에서 복사할 수 있도록 링크 반환(1회성)
        ]);
    }

    public function resend(Invitation $invitation, SendInvitationAction $action): JsonResponse
    {
        // 재발송 = 같은 대상으로 새 토큰 발급 (기존 대기 초대는 자동 철회)
        $result = $action->execute(
            $invitation->email,
            $invitation->roleEnum(),
            Auth::user(),
            $invitation->name,
            $invitation->assigned_agency_id,
        );

        return response()->json(['ok' => true, 'url' => $result['url']]);
    }

    public function revoke(Invitation $invitation): JsonResponse
    {
        if (! $invitation->isPending()) {
            return response()->json(['ok' => false, 'message' => '대기 중인 초대만 철회할 수 있습니다.'], 422);
        }
        $invitation->forceFill(['revoked_at' => now()])->save();

        return response()->json(['ok' => true]);
    }
}
