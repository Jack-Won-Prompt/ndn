<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AcceptInvitationAction;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 초대 수락(계정 설정) — 공개(비인증) 흐름.
 *
 * 유효한 대기 초대만 수락 폼을 보여준다. 수락 시 조직 사용자 계정을 만들고
 * 포털 로그인으로 안내한다.
 */
class InvitationAcceptController extends Controller
{
    public function show(string $token): View
    {
        $invitation = Invitation::forToken($token)->first();

        if ($invitation === null || ! $invitation->isPending()) {
            return view('invitations.invalid');
        }

        return view('invitations.accept', [
            'token' => $token,
            'email' => $invitation->email,
            'roleLabel' => $invitation->roleEnum()->label(),
            'name' => $invitation->name,
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token, AcceptInvitationAction $action): RedirectResponse
    {
        $action->execute($token, $request->string('name')->value(), $request->string('password')->value());

        return redirect()->route('portal.login')
            ->with('status', '계정이 생성되었습니다. 로그인해 주세요.');
    }
}
