<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Actions;

use App\Domains\Onboarding\Models\ConsentRecord;
use App\Domains\Recruitment\Models\Worker;
use App\Shared\Enums\ConsentPurpose;

/**
 * 동의 부여 (CLAUDE.md §7-4).
 *
 * 목적·기관·항목별로 행을 분리 생성한다. 이미 동일 조합의 활성 동의가 있으면 그대로 둔다.
 */
class GrantConsentAction
{
    public function execute(
        Worker $worker,
        ConsentPurpose $purpose,
        string $item,
        ?string $agencyType = null,
        ?int $agencyId = null,
    ): ConsentRecord {
        $existing = $worker->consents()
            ->active()
            ->where('purpose', $purpose->value)
            ->where('item', $item)
            ->where('agency_type', $agencyType)
            ->where('agency_id', $agencyId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $worker->consents()->create([
            'purpose' => $purpose,
            'item' => $item,
            'agency_type' => $agencyType,
            'agency_id' => $agencyId,
            'granted_at' => now(),
        ]);
    }
}
