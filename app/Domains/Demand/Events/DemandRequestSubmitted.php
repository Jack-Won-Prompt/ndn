<?php

declare(strict_types=1);

namespace App\Domains\Demand\Events;

use App\Domains\Demand\Models\DemandRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 농가가 수요 신청을 제출했을 때 발생. 시청 담당자 알림 트리거에 사용.
 */
class DemandRequestSubmitted
{
    use Dispatchable;

    public function __construct(public readonly DemandRequest $demandRequest) {}
}
