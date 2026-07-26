<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Http\Resources;

use App\Domains\Monitoring\Models\MonthlyInterview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 근로자 앱 — 근로 생활 평가 응답 (CLAUDE.md §9).
 *
 * 점검자 신원(inspector_user_id·이름)은 근로자 앱에 노출하지 않는다.
 *
 * @mixin MonthlyInterview
 */
class MonthlyInterviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = [];
        foreach (MonthlyInterview::ITEMS as $item) {
            $items[$item] = (bool) $this->{$item};
        }

        return [
            'id' => $this->id,
            'interviewed_on' => $this->interviewed_on?->toDateString(),
            'source' => $this->source->value,
            'items' => $items,
            'risk_score' => $this->risk_score,
            'risk_level' => $this->risk_level->value,
            'memo' => $this->memo,
        ];
    }
}
