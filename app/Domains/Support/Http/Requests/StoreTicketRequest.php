<?php

declare(strict_types=1);

namespace App\Domains\Support\Http\Requests;

use App\Domains\Support\Enums\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * 근로자 민원 접수 입력 검증 (업무흐름 §8).
 */
class StoreTicketRequest extends FormRequest
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
            'type' => ['required', new Enum(TicketType::class)],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
