<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domains\Settlement\Actions\ProcessSettlementAction;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementDocument;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Settlement\Notifications\SettlementAssignedNotification;
use App\Domains\Settlement\Support\DocumentWatermark;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 제휴 대리점 포털 — 배정된 정착 서비스 건 조회·처리 (CLAUDE.md §7-4·§7-5).
 *
 * PartnerAgencyScope(전역 스코프)가 쿼리를 배정 건으로 1차 제한하고,
 * SettlementRequestPolicy 가 직접 접근을 2차로 막는다(이중 방어).
 * 문서 다운로드에는 대리점명 워터마크를 삽입한다.
 */
class PartnerSettlementController extends Controller
{
    private const DISK = 'local';

    /** 배정된 건 목록 + 새 배정 알림 읽음 처리 */
    public function index(Request $request): View
    {
        $user = $request->user();

        // 스코프가 assigned_agency_id 로 자동 제한 → 자기 배정 건만 조회됨
        $rows = SettlementRequest::with('worker')
            ->withCount('documents')
            ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
            ->orderBy('sla_due_at')
            ->latest('id')
            ->get();

        // 배정 알림 읽음 처리(목록 진입 시)
        $user->unreadNotifications()
            ->where('type', SettlementAssignedNotification::class)
            ->update(['read_at' => now()]);

        return view('portal.settlements.index', [
            'rows' => $rows,
            'statuses' => SettlementStatus::cases(),
        ]);
    }

    /** 배정 건 상세 (근로자 정보 열람 → §7-6 감사 로그) */
    public function show(Request $request, SettlementRequest $settlement): View
    {
        $this->authorize('view', $settlement);
        $settlement->load(['worker', 'documents']);

        if ($settlement->worker) {
            $settlement->worker->recordAccessBy($request->user(), 'partner-settlement');
        }

        return view('portal.settlements.show', ['s' => $settlement]);
    }

    /** 상태 처리 (배정 → 처리 중 → 완료) */
    public function process(Request $request, SettlementRequest $settlement, ProcessSettlementAction $action): RedirectResponse
    {
        $this->authorize('process', $settlement);

        $data = $request->validate([
            'target' => ['required', Rule::in([SettlementStatus::Processing->value, SettlementStatus::Done->value])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $action->execute($settlement, SettlementStatus::from($data['target']), $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', '처리 상태를 갱신했습니다.');
    }

    /** 처리 증빙 문서 업로드 (private 저장) */
    public function uploadDocument(Request $request, SettlementRequest $settlement): RedirectResponse
    {
        $this->authorize('uploadDocument', $settlement);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
        ]);

        $file = $request->file('file');
        $path = $file->store("settlement/{$settlement->id}", self::DISK);

        $settlement->documents()->create([
            'uploaded_by' => $request->user()->id,
            'disk_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('status', '증빙 문서를 업로드했습니다.');
    }

    /** 문서 다운로드 — 대리점명 워터마크 삽입(§7-5). 이미지가 아니면 원본 스트리밍. */
    public function downloadDocument(Request $request, SettlementRequest $settlement, SettlementDocument $document): StreamedResponse
    {
        $this->authorize('downloadDocument', $settlement);
        abort_unless($document->settlement_request_id === $settlement->id, 404);
        abort_unless(Storage::disk(self::DISK)->exists($document->disk_path), 404);

        $label = Auth::user()->name;
        $binary = Storage::disk(self::DISK)->get($document->disk_path);

        if ($document->isImage()) {
            $stamped = DocumentWatermark::stampImage($binary, $label);
            if ($stamped !== null) {
                return response()->streamDownload(
                    fn () => print ($stamped),
                    $document->original_name,
                    ['Content-Type' => 'image/png'],
                );
            }
        }

        // 이미지가 아니거나 워터마크 실패 시: 파일명에 대리점 표기를 남겨 출처를 명시
        return response()->streamDownload(
            fn () => print ($binary),
            '['.$label.'] '.$document->original_name,
            ['Content-Type' => $document->mime ?: 'application/octet-stream'],
        );
    }
}
