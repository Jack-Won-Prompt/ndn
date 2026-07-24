<?php

declare(strict_types=1);

namespace App\Domains\Demand\Actions;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Events\DemandRequestSubmitted;
use App\Domains\Demand\Models\DemandRequest;
use RuntimeException;

/**
 * 농가 수요 신청 제출 (draft → submitted).
 *
 * 상태 전이는 DemandStatus::canTransitionTo() 로만 검증한다 (문자열 하드코딩 금지, §12).
 */
class SubmitDemandRequestAction
{
    public function execute(DemandRequest $demandRequest): DemandRequest
    {
        if (! $demandRequest->status->canTransitionTo(DemandStatus::Submitted)) {
            throw new RuntimeException(
                "제출할 수 없는 상태입니다: {$demandRequest->status->value}"
            );
        }

        $demandRequest->update([
            'status' => DemandStatus::Submitted,
            'submitted_at' => now(),
        ]);

        DemandRequestSubmitted::dispatch($demandRequest);

        return $demandRequest;
    }
}
