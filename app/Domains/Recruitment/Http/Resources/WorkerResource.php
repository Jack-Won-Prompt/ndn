<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 근로자 앱용 본인 프로필 응답 (CLAUDE.md §9).
 *
 * 민감 필드(여권번호 등)는 앱 프로필에 그대로 노출하지 않는다. 필요한 최소 정보만.
 */
class WorkerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nationality' => $this->nationality,
            'locale' => $this->locale,
            'status' => $this->status,
        ];
    }
}
