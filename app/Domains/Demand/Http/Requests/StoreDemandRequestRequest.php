<?php

declare(strict_types=1);

namespace App\Domains\Demand\Http\Requests;

use App\Shared\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * 수요 신청 생성 입력 검증 (CLAUDE.md §11: 입력 검증은 Form Request).
 */
class StoreDemandRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 인가는 컨트롤러의 Policy 로 처리한다. 여기서는 통과.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'nationality' => ['required', 'string', 'size:2'],
            'headcount' => ['required', 'integer', 'min:1', 'max:999'],
            'age_min' => ['nullable', 'integer', 'min:18', 'max:70'],
            'age_max' => ['nullable', 'integer', 'min:18', 'max:70', 'gte:age_min'],
            'gender' => ['required', new Enum(Gender::class)],
            'allow_siblings' => ['boolean'],
            'crop' => ['required', 'string', 'max:100'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
