<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Models\AccountDeletionRequest;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 계정 삭제 요청 처리 (Google Play 데이터 삭제 정책, §7-7).
 *
 * 공개 페이지에서 접수된 요청을 관리자가 확인·처리한다. 실제 계정 파기는
 * 대상 근로자를 soft delete → workers:purge-expired 잡이 90일 후 민감정보 파기.
 */
class AccountDeletionAdminController extends Controller
{
    /**
     * 화면용 목록 (대기 우선, 최근순).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(): array
    {
        return AccountDeletionRequest::query()
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('id')
            ->limit(500)
            ->get()
            ->map(fn (AccountDeletionRequest $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'email' => $r->email,
                'reason' => $r->reason,
                'status' => $r->status,
                'requested' => LocalTime::format($r->created_at),
                'processed' => $r->processed_at ? LocalTime::format($r->processed_at) : null,
                'admin_note' => $r->admin_note,
            ])->all();
    }

    /** 요청 처리(완료/거절) 상태 갱신 */
    public function process(Request $request, AccountDeletionRequest $accountDeletionRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                AccountDeletionRequest::STATUS_COMPLETED,
                AccountDeletionRequest::STATUS_REJECTED,
            ])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $accountDeletionRequest->forceFill([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }
}
