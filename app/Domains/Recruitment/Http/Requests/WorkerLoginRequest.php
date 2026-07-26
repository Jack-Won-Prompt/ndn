<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 근로자 앱 로그인 입력 검증 (CLAUDE.md §9, §11).
 */
class WorkerLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            // 기기 식별용 토큰 이름 (선택). 기기별 로그아웃을 위해 남긴다.
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
