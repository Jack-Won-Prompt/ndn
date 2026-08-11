<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Http\Controllers\Api;

use App\Domains\Onboarding\Actions\AgreeToRequiredDocumentsAction;
use App\Domains\Onboarding\Models\RequiredDocument;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 근로자 앱 — 필수 확인·동의 문서 (CLAUDE.md §6, §9).
 *
 * 동의 게이트(EnsureRequiredDocumentsAgreed) 밖에 둔다. 안에 두면 미동의 상태에서
 * 동의 화면 자체를 열 수 없어 잠긴다.
 */
class RequiredDocumentController extends Controller
{
    /** 전문 + 동의 여부 (근로자 본인 언어로) */
    public function index(Request $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();
        $locale = $worker->locale ?: 'ko';

        $documents = RequiredDocument::active()->get();
        $pendingIds = RequiredDocument::pendingFor($worker)->pluck('id')->all();

        return response()->json([
            'data' => $documents->map(fn (RequiredDocument $d) => [
                'id' => $d->id,
                'code' => $d->code,
                'title' => $d->title($locale),
                'body' => $d->body($locale),
                'version' => $d->version,
                'required' => $d->required,
                'agreed' => ! in_array($d->id, $pendingIds, true),
                // 원본을 받아 읽어야 하는 서식이면 내려받기 주소를 함께 준다.
                'download_url' => $d->hasFile()
                    ? url('api/v1/required-documents/'.$d->id.'/file') : null,
            ])->all(),
            'meta' => [
                'locale' => $locale,
                // true 면 앱이 다음 화면으로 넘어가도 된다
                'all_agreed' => $pendingIds === [],
                'pending_count' => count($pendingIds),
            ],
        ]);
    }

    /**
     * 원본 파일 내려받기 (근로 동의서 등 서명 서식).
     *
     * 파일명은 근로자 언어로 짓는다 — 한글 파일명을 그대로 주면 어느 문서인지 모른다.
     * 한글·벵골어·키릴 파일명이 브라우저에서 깨지지 않도록 RFC 5987 로 인코딩한다.
     * 파일은 public/ 밖에 있으므로 이 라우트를 통해서만 나간다.
     */
    public function download(Request $request, RequiredDocument $requiredDocument): StreamedResponse
    {
        abort_unless($requiredDocument->active, 404);
        abort_unless($requiredDocument->hasFile(), 404, '원본 파일이 없습니다.');

        /** @var Worker $worker */
        $worker = $request->user();
        $locale = $worker->locale ?: 'ko';

        return Storage::disk(RequiredDocument::DISK)
            ->download($requiredDocument->file, $requiredDocument->downloadName($locale));
    }

    /** 동의 제출 — 체크한 문서들을 현재 버전으로 기록한다. */
    public function agree(Request $request, AgreeToRequiredDocumentsAction $action): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $data = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer', 'exists:required_documents,id'],
        ]);

        try {
            $action->execute($worker, $data['document_ids']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $pending = RequiredDocument::pendingFor($worker);

        return response()->json([
            'data' => [
                'all_agreed' => $pending->isEmpty(),
                'pending' => $pending->pluck('code')->all(),
            ],
        ]);
    }
}
