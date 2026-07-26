<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Shared\Enums\UserRole;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * 조직 초대 발송 (초대 전용 가입, CLAUDE.md §7-3).
 *
 * 초대 가능한 역할은 조직 주체(시청·농가·송출·대리점)뿐이다. 근로자는 셀프 가입,
 * NDN 관리자는 ndn:create-admin 으로만 만든다. 같은 이메일의 대기 초대는 재발송 시 철회한다.
 * 토큰 평문은 저장하지 않고(해시만) 링크로만 전달하며, 알림 본문에는 개인정보를 넣지 않는다.
 */
class SendInvitationAction
{
    /** 초대로 만들 수 있는 역할 */
    public const INVITABLE = [
        UserRole::CityOfficer,
        UserRole::FarmOwner,
        UserRole::SendingAgency,
        UserRole::PartnerAgency,
    ];

    /**
     * @return array{invitation: Invitation, token: string, url: string}
     *
     * @throws ValidationException 초대 불가 역할이거나 이미 가입된 이메일일 때
     */
    public function execute(
        string $email,
        UserRole $role,
        User $invitedBy,
        ?string $name = null,
        ?int $assignedAgencyId = null,
        int $expiresInDays = 7,
    ): array {
        if (! in_array($role, self::INVITABLE, true)) {
            throw ValidationException::withMessages([
                'role' => ['초대할 수 없는 역할입니다. 조직(시청·농가·송출·대리점)만 초대할 수 있습니다.'],
            ]);
        }

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['이미 가입된 이메일입니다.'],
            ]);
        }

        // 같은 이메일의 기존 대기 초대는 철회하고 새로 발급한다.
        Invitation::where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        // 평문 토큰은 소문자 hex (개인정보 패턴과 겹치지 않음) — 해시만 저장한다.
        $plain = bin2hex(random_bytes(20));

        $invitation = Invitation::create([
            'email' => $email,
            'name' => $name,
            'role' => $role->value,
            'assigned_agency_id' => $assignedAgencyId,
            'token' => Invitation::hashToken($plain),
            'invited_by' => $invitedBy->id,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        $url = route('invite.show', ['token' => $plain]);

        // 개인정보 없는 초대 이메일 (베스트에포트 — 실패해도 초대는 유효, 콘솔에서 링크 복사 가능)
        try {
            Notification::route('mail', $email)
                ->notify(new InvitationNotification($url, $role->label()));
        } catch (\Throwable $e) {
            report($e);
        }

        return ['invitation' => $invitation, 'token' => $plain, 'url' => $url];
    }
}
