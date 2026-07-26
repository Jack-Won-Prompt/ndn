<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Http\Requests;

use App\Domains\Monitoring\Models\MonthlyInterview;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 근로 생활 자가 평가 입력 검증 (업무흐름 §7).
 *
 * 6개 항목은 모두 boolean(true = 양호)이며 필수다. 미응답 항목을 양호로
 * 간주하면 리스크가 과소평가되므로 앱에서 전부 받도록 강제한다.
 */
class StoreSelfAssessmentRequest extends FormRequest
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
        $rules = ['memo' => ['nullable', 'string', 'max:2000']];

        foreach (MonthlyInterview::ITEMS as $item) {
            $rules[$item] = ['required', 'boolean'];
        }

        return $rules;
    }
}
