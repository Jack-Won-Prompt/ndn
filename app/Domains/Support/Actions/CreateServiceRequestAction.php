<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Support\Enums\ServiceRequestStatus;
use App\Domains\Support\Models\ServiceRequest;
use App\Models\User;

/**
 * SR 등록 — 콘솔 사용자가 시스템 개선·오류를 요청한다.
 */
class CreateServiceRequestAction
{
    public function execute(User $requester, string $title, string $body): ServiceRequest
    {
        return ServiceRequest::create([
            'requester_user_id' => $requester->id,
            'title' => $title,
            'body' => $body,
            'status' => ServiceRequestStatus::Received,
        ]);
    }
}
