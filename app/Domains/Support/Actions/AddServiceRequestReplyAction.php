<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Support\Enums\ServiceRequestStatus;
use App\Domains\Support\Models\ServiceRequest;
use App\Domains\Support\Models\ServiceRequestReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * SR 답글 등록.
 *
 * 등록자가 아닌 사람이 처음 답글을 달면 그 사람이 담당자가 되고, 접수 상태이던
 * SR 은 처리 중으로 바뀐다 — 담당자 지정을 따로 하지 않아도 흐름이 이어지게 한다.
 * 이미 종료(적용 완료·반려)된 SR 에도 답글은 남길 수 있으나 상태는 건드리지 않는다.
 */
class AddServiceRequestReplyAction
{
    public function execute(ServiceRequest $sr, User $author, string $body): ServiceRequestReply
    {
        return DB::transaction(function () use ($sr, $author, $body) {
            $reply = ServiceRequestReply::create([
                'service_request_id' => $sr->id,
                'user_id' => $author->id,
                'body' => $body,
            ]);

            $isRequester = $author->id === $sr->requester_user_id;

            if (! $isRequester && $sr->assignee_user_id === null) {
                $sr->assignee_user_id = $author->id;
            }

            if (! $isRequester && $sr->status === ServiceRequestStatus::Received) {
                $sr->status = ServiceRequestStatus::InProgress;
            }

            $sr->save();

            return $reply;
        });
    }
}
