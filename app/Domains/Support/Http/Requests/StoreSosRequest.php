<?php

declare(strict_types=1);

namespace App\Domains\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SOS 요청 입력 검증 (CLAUDE.md §9).
 *
 * 좌표는 선택값이며 이 요청 본문으로만 받는다. 위치가 없어도 SOS 는 접수된다.
 */
class StoreSosRequest extends FormRequest
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
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
