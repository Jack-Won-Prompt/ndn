<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 초대 수락(계정 설정) 요청 검증.
 */
class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 토큰 유효성은 Action 에서 확인
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
