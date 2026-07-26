<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 근로자 셀프 가입 요청 검증 (CLAUDE.md §9, §11: 입력 검증은 Form Request).
 *
 * 민감 필드(passport_no/birth_date/phone_home_country)는 검증만 하고
 * 저장 시 encrypted cast 로 암호화된다(§7-1). 위치정보는 받지 않는다(§7-2).
 */
class RegisterWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 가입은 비인증 공개 엔드포인트
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:workers,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nationality' => ['required', 'string', 'size:2'],
            'locale' => ['required', 'in:ko,bn,lo,si,vi'],
            'passport_no' => ['required', 'string', 'max:64'],
            'birth_date' => ['nullable', 'date'],
            'phone_home_country' => ['nullable', 'string', 'max:40'],
        ];
    }
}
