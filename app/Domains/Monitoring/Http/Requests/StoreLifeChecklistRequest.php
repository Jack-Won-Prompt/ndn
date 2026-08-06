<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 한국 생활 체크리스트 저장 요청.
 *
 * 체크된 항목 전체를 받는다. 하나도 체크하지 않은 상태(전부 해제)도 정상이므로
 * 빈 배열을 허용한다.
 */
class StoreLifeChecklistRequest extends FormRequest
{
    /** 인가는 라우트 미들웨어(auth:sanctum + worker)가 이미 한다. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'checked' => ['present', 'array'],
            'checked.*' => ['integer', 'exists:life_checklist_items,id'],
        ];
    }

    /** @return list<int> */
    public function checkedIds(): array
    {
        return array_map('intval', $this->input('checked', []));
    }
}
