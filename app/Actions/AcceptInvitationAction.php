<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 초대 수락 → 조직 사용자 계정 생성 (초대 전용 가입).
 *
 * 유효한(대기) 초대만 수락 가능하다. 계정 생성과 초대 수락 처리는 한 트랜잭션으로 묶는다.
 * 2FA 필수 역할(대리점)의 2FA 등록은 로그인 후 Fortify 흐름에서 강제된다(§2).
 */
class AcceptInvitationAction
{
    /**
     * @return array{user: User, invitation: Invitation}
     *
     * @throws ValidationException 토큰이 유효하지 않거나 이미 처리된 초대일 때
     */
    public function execute(string $token, string $name, string $password): array
    {
        $invitation = Invitation::forToken($token)->first();

        if ($invitation === null || ! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => ['유효하지 않거나 만료된 초대입니다.'],
            ]);
        }

        if (User::where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['이미 가입된 이메일입니다.'],
            ]);
        }

        return DB::transaction(function () use ($invitation, $name, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,                    // hashed cast
                'locale' => 'ko',
                'assigned_agency_id' => $invitation->assigned_agency_id,
            ]);
            $user->assignRole($invitation->roleEnum()->value);

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
                'name' => $invitation->name ?: $name,
            ])->save();

            return ['user' => $user, 'invitation' => $invitation];
        });
    }
}
