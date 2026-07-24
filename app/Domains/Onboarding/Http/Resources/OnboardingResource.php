<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Http\Resources;

use App\Domains\Onboarding\Models\OnboardingSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 근로자 앱 — 온보딩 제출물 응답 (CLAUDE.md §9).
 *
 * @mixin OnboardingSubmission
 */
class OnboardingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'editable' => $this->status->isEditableByWorker(),
            'payload' => $this->payload,
            'review_note' => $this->review_note,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];
    }
}
