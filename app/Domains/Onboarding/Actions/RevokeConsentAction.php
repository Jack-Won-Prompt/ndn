<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Actions;

use App\Domains\Onboarding\Models\ConsentRecord;

/**
 * 동의 철회 (CLAUDE.md §7-4).
 *
 * 행을 삭제하지 않고 revoked_at 을 채워 이력을 보존한다.
 */
class RevokeConsentAction
{
    public function execute(ConsentRecord $consent): ConsentRecord
    {
        if ($consent->isActive()) {
            $consent->update(['revoked_at' => now()]);
        }

        return $consent;
    }
}
