<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Actions\AddServiceRequestReplyAction;
use App\Domains\Support\Actions\ChangeServiceRequestStatusAction;
use App\Domains\Support\Actions\CreateServiceRequestAction;
use App\Domains\Support\Enums\ServiceRequestStatus;
use App\Domains\Support\Models\ServiceRequest;
use App\Domains\Support\Models\ServiceRequestReply;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * SR(Service Request) — 콘솔 상단 SR 버튼으로 여는 요청 창구.
 *
 * 등록 → 담당자 답글 → 상태 관리(적용 완료/반려) 흐름을 담당한다.
 * 비즈니스 로직은 Action 에 있고 여기서는 검증·호출·응답만 한다(§4, §11).
 */
class ServiceRequestController extends Controller
{
    /** 목록 (최근 순). 화면 초기 렌더용. */
    public static function rows(): array
    {
        return ServiceRequest::with(['requester:id,name', 'assignee:id,name'])
            ->withCount('replies')
            ->latest('id')->limit(500)->get()
            ->map(fn (ServiceRequest $sr) => [
                'id' => $sr->id,
                'title' => $sr->title,
                'status' => $sr->status->value,
                'status_label' => $sr->status->label(),
                'requester' => $sr->requester?->name,
                'assignee' => $sr->assignee?->name,
                'replies' => $sr->replies_count,
                'created' => LocalTime::format($sr->created_at),
                'completed' => $sr->completed_at ? LocalTime::format($sr->completed_at) : null,
            ])->all();
    }

    /** @return list<array{value:string,label:string}> */
    public static function statusOptions(): array
    {
        return ServiceRequestStatus::options();
    }

    /** SR 등록 */
    public function store(Request $request, CreateServiceRequestAction $action): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $sr = $action->execute(Auth::user(), $data['title'], $data['body']);

        return response()->json([
            'ok' => true,
            'message' => "SR #{$sr->id} 로 등록했습니다.",
            'rows' => self::rows(),
        ]);
    }

    /** SR 상세 + 답글 목록 */
    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest->load([
            'requester:id,name',
            'assignee:id,name',
            'replies.author:id,name',
        ]);

        return response()->json([
            'id' => $serviceRequest->id,
            'title' => $serviceRequest->title,
            'body' => $serviceRequest->body,
            'status' => $serviceRequest->status->value,
            'status_label' => $serviceRequest->status->label(),
            'requester' => $serviceRequest->requester?->name,
            'assignee' => $serviceRequest->assignee?->name,
            'created' => LocalTime::format($serviceRequest->created_at),
            'completed' => $serviceRequest->completed_at
                ? LocalTime::format($serviceRequest->completed_at) : null,
            // 현재 상태에서 고를 수 있는 다음 상태만 내려준다.
            'transitions' => array_map(
                fn (ServiceRequestStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $serviceRequest->status->allowedTransitions(),
            ),
            'replies' => $serviceRequest->replies
                ->map(fn (ServiceRequestReply $r) => [
                    'id' => $r->id,
                    'author' => $r->author?->name,
                    'body' => $r->body,
                    'created' => LocalTime::format($r->created_at),
                ])->all(),
        ]);
    }

    /** 담당자 답글 */
    public function reply(Request $request, ServiceRequest $serviceRequest, AddServiceRequestReplyAction $action): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $action->execute($serviceRequest, Auth::user(), $data['body']);

        return response()->json(['ok' => true, 'message' => '답글을 등록했습니다.']);
    }

    /** 상태 변경 — 적용 완료로 바꾸면 등록자에게 이메일이 나간다. */
    public function updateStatus(Request $request, ServiceRequest $serviceRequest, ChangeServiceRequestStatusAction $action): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(ServiceRequestStatus::cases(), 'value'))],
        ]);

        try {
            $action->execute($serviceRequest, ServiceRequestStatus::from($data['status']), Auth::user());
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $message = $serviceRequest->status === ServiceRequestStatus::Completed
            ? '적용 완료로 변경하고 등록자에게 이메일을 발송했습니다.'
            : '상태를 변경했습니다.';

        return response()->json([
            'ok' => true,
            'message' => $message,
            'rows' => self::rows(),
        ]);
    }
}
