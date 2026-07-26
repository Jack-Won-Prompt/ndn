<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Recruitment\Actions\ApproveWorkerAction;
use App\Domains\Recruitment\Actions\RejectWorkerAction;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 근로자 셀프 가입 승인 큐 (관리자 승인제).
 *
 * pending 상태의 근로자를 나열하고 승인/거절한다. 상태 전이는 Action 이 담당하며
 * 감사 로그를 남긴다(§7-6).
 */
class SignupApprovalController extends Controller
{
    /** 승인 대기(pending) 근로자 목록. */
    public static function rows(): array
    {
        return Worker::where('status', WorkerStatus::Pending->value)
            ->latest('id')->limit(500)->get()
            ->map(fn (Worker $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'email' => $w->email,
                'nationality' => $w->nationality,
                'locale' => $w->locale,
                'registered' => $w->created_at?->format('Y-m-d H:i'),
            ])->all();
    }

    public function approve(Request $request, Worker $worker, ApproveWorkerAction $action): JsonResponse
    {
        try {
            $action->execute($worker, Auth::user());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'status' => $worker->status->value]);
    }

    public function reject(Request $request, Worker $worker, RejectWorkerAction $action): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $action->execute($worker, Auth::user(), $data['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'status' => $worker->status->value]);
    }
}
