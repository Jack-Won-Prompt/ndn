<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Models\AccountDeletionRequest;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                // 표에는 참/거짓이나 코드가 아니라 읽을 글자가 필요하다 (엑셀로도 그대로 나간다).
                'status_label' => self::STATUS_LABELS[$r->status] ?? $r->status,
                'requested_at' => LocalTime::format($r->created_at),
                'processed' => $r->processed_at ? LocalTime::format($r->processed_at) : null,
                'admin_note' => $r->admin_note,
            ])->all();
    }

    /** 상태 선택지 — 표의 콤보와 라벨이 한 곳에서 온다. */
    public const STATUS_LABELS = [
        AccountDeletionRequest::STATUS_PENDING => '대기',
        AccountDeletionRequest::STATUS_COMPLETED => '완료',
        AccountDeletionRequest::STATUS_REJECTED => '거절',
    ];

    /** @return array<int, array{value: string, label: string}> */
    public static function statusOptions(): array
    {
        return collect(self::STATUS_LABELS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()->all();
    }

    /**
     * 표에서 고친 행을 저장한다 (수요 신청 화면과 같은 [변경 저장] 흐름).
     *
     * 새로 만들지는 않는다 — 삭제 요청은 본인이 공개 페이지에서 접수하는 것이고,
     * 관리자가 대신 만들면 '본인이 요청했다' 는 증빙이 사라진다.
     */
    public function save(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'updated' => ['array'],
            'updated.*.current.id' => ['required', 'integer', 'exists:account_deletion_requests,id'],
            'updated.*.current.status' => ['required', Rule::in(array_keys(self::STATUS_LABELS))],
            'updated.*.current.admin_note' => ['nullable', 'string', 'max:1000'],
            'deleted' => ['array'],
            'deleted.*.id' => ['integer', 'exists:account_deletion_requests,id'],
        ]);

        DB::transaction(function () use ($payload) {
            $del = collect($payload['deleted'] ?? [])->pluck('id')->filter()->all();

            if ($del) {
                // 접수 기록을 지우는 일이라 누가 지웠는지는 남긴다(§7-6).
                activity('account-deletion')
                    ->causedBy(Auth::user())
                    ->withProperties(['ids' => $del, 'count' => count($del)])
                    ->log('계정 삭제 요청 삭제');

                AccountDeletionRequest::whereIn('id', $del)->delete();
            }

            foreach ($payload['updated'] ?? [] as $u) {
                $cur = $u['current'];
                $before = AccountDeletionRequest::findOrFail($cur['id']);

                $processed = $cur['status'] !== AccountDeletionRequest::STATUS_PENDING;

                $before->forceFill([
                    'status' => $cur['status'],
                    'admin_note' => $cur['admin_note'] ?? null,
                    // 대기로 되돌리면 처리 흔적도 지운다 — 남겨 두면 '언제 처리했나' 가 거짓이 된다.
                    'processed_by' => $processed ? Auth::id() : null,
                    'processed_at' => $processed ? ($before->processed_at ?? now()) : null,
                ])->save();
            }
        });

        return response()->json(['ok' => true, 'message' => '저장했습니다.', 'rows' => self::rows()]);
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
