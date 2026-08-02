<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Actions\ApproveWorkerAction;
use App\Domains\Recruitment\Actions\RejectWorkerAction;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
        return Worker::with('city:id,name')
            ->where('status', WorkerStatus::Pending->value)
            ->latest('id')->limit(500)->get()
            ->map(fn (Worker $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'email' => $w->email,
                'nationality' => $w->nationality,
                // 지역별로 모집 정원이 다르므로 승인 판단에 필요하다
                'city' => $w->city?->name,
                'locale' => $w->locale,
                'registered' => LocalTime::format($w->created_at),
            ])->all();
    }

    /** 가입 신청 상세 (본인 정보 + 제출 서류) — 상세 탭용. 개인정보 열람 감사(§7-6). */
    public function show(Worker $worker): JsonResponse
    {
        $worker->recordAccessBy(Auth::user(), 'signup-detail');
        $worker->loadMissing('city');

        $sub = OnboardingSubmission::where('worker_id', $worker->id)->latest('id')->first();
        $hasSig = $sub && filled($sub->signature_path) && Storage::disk('local')->exists($sub->signature_path);

        return response()->json([
            'id' => $worker->id,
            'name' => $worker->name,
            'email' => $worker->email,
            'nationality' => $worker->nationality,
            'city' => $worker->city?->name,
            'locale' => $worker->locale,
            'status' => $worker->status->value,
            'registered' => LocalTime::format($worker->created_at),
            'onboarding' => $sub ? [
                'status' => $sub->status->label(),
                'payload' => $sub->payload ?? [],
                'has_signature' => $hasSig,
                'signature_url' => $hasSig ? route('admin.onboarding.signature', $sub) : null,
            ] : null,
        ]);
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
