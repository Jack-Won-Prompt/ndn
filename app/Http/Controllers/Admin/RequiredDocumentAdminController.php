<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Onboarding\Models\RequiredDocument;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 필수 확인·동의 문서 관리 (근로자 의무사항·표준근로계약서 등).
 *
 * 본문은 법적 문안이라 코드가 아니라 여기서 입력한다. 언어별로 따로 저장하며,
 * 문안을 고치면 '새 버전으로 저장' 으로 version 을 올려 전원에게 재동의를 받는다.
 */
class RequiredDocumentAdminController extends Controller
{
    /** @return array<int, array<string, mixed>> */
    public static function rows(): array
    {
        return RequiredDocument::query()
            ->withCount(['consents as agreed_count' => fn ($q) => $q->whereColumn(
                'document_consents.version', 'required_documents.version',
            )])
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (RequiredDocument $d) => [
                'id' => $d->id,
                'code' => $d->code,
                'title' => $d->title('ko'),
                'version' => $d->version,
                'required' => $d->required,
                'active' => $d->active,
                // 5개 언어 중 본문까지 채워진 언어 (§6: 근로자 대상은 5개 언어 필수)
                'filled' => collect(RequiredDocument::LOCALES)
                    ->filter(fn (string $l) => $d->hasTranslation($l))->values()->all(),
                'agreed' => $d->agreed_count,
                // 원본이 붙은 문서는 본문 대신 파일을 받아 읽는다(근로 동의서 등).
                'file' => $d->file,
                'file_url' => $d->hasFile() ? route('admin.required-documents.file', $d) : null,
                'updated' => LocalTime::format($d->updated_at),
            ])->all();
    }

    /**
     * 원본 서식 내려받기 (관리자용).
     *
     * 근로자에게 내려가는 것과 같은 파일이다. 관리자는 한국어 파일명으로 받는다.
     * 파일은 public/ 밖에 있으므로 이 라우트를 통해서만 나간다.
     */
    public function download(RequiredDocument $requiredDocument): StreamedResponse
    {
        abort_unless($requiredDocument->hasFile(), 404, '원본 파일이 없습니다.');

        return Storage::disk(RequiredDocument::DISK)
            ->download($requiredDocument->file, $requiredDocument->downloadName('ko'));
    }

    /**
     * 원본 서식 올리기 / 바꾸기.
     *
     * 법적 서식은 화면에 옮겨 적지 않고 원본을 그대로 받게 한다(§근로 동의서와 같은
     * 방식). 옮겨 적으면 문안이 원본과 달라질 수 있고 그건 법적 문서에서 사고다.
     * 이걸 붙여야 본문을 타이핑하지 않고도 문서를 켤 수 있다.
     */
    public function uploadFile(Request $request, RequiredDocument $requiredDocument): JsonResponse
    {
        $request->validate([
            // 원본은 PDF·워드·한글 서식으로 온다. 이미지·실행 파일은 받지 않는다.
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,hwp,hwpx'],
        ]);

        $upload = $request->file('file');
        $ext = strtolower($upload->getClientOriginalExtension());

        // 저장 이름은 ASCII 로 짓는다 — 원본 파일명이 한글이면 서버·백업에서 깨진다.
        // 근로자에게는 어차피 자기 언어의 제목으로 내려간다(downloadName).
        $name = $requiredDocument->code.'_'.Str::random(8).'.'.$ext;
        $upload->storeAs('', $name, ['disk' => RequiredDocument::DISK]);

        $previous = $requiredDocument->file;
        $requiredDocument->file = $name;
        $requiredDocument->save();

        // 예전 파일은 지우지 않는다. 이미 그 서식에 동의한 근로자가 있으면
        // 무엇에 동의했는지가 남아 있어야 한다.
        activity('required-document')
            ->performedOn($requiredDocument)
            ->causedBy(Auth::user())
            ->withProperties(['file' => $name, 'previous' => $previous])
            ->log('필수 문서 원본 서식 올림');

        return response()->json([
            'ok' => true,
            'message' => '원본 서식을 올렸습니다.',
            'file' => $name,
            'file_url' => route('admin.required-documents.file', $requiredDocument),
            'rows' => self::rows(),
        ]);
    }

    /**
     * 붙여 둔 원본 떼기.
     *
     * 켜져 있는데 본문도 없는 문서에서 파일을 떼면 근로자가 빈 화면에 동의하게
     * 된다. 그건 막는다.
     */
    public function removeFile(RequiredDocument $requiredDocument): JsonResponse
    {
        if ($requiredDocument->active && ! filled($requiredDocument->body('ko'))) {
            return response()->json([
                'ok' => false,
                'message' => '사용 중이고 본문도 없는 문서입니다. 먼저 사용을 끄거나 본문을 입력하세요.',
            ], 422);
        }

        $previous = $requiredDocument->file;
        $requiredDocument->file = null;
        $requiredDocument->save();

        // 파일 자체는 남긴다 — 이미 동의한 근로자가 무엇에 동의했는지의 증빙이다.
        activity('required-document')
            ->performedOn($requiredDocument)
            ->causedBy(Auth::user())
            ->withProperties(['previous' => $previous])
            ->log('필수 문서 원본 서식 뗌');

        return response()->json(['ok' => true, 'message' => '원본 서식을 뗐습니다.', 'rows' => self::rows()]);
    }

    /** 언어별 본문 (편집 화면용) */
    public function show(RequiredDocument $requiredDocument): JsonResponse
    {
        $t = $requiredDocument->translations ?? [];

        return response()->json([
            'id' => $requiredDocument->id,
            'code' => $requiredDocument->code,
            'version' => $requiredDocument->version,
            'required' => $requiredDocument->required,
            'active' => $requiredDocument->active,
            'file' => $requiredDocument->file,
            'file_url' => $requiredDocument->hasFile()
                ? route('admin.required-documents.file', $requiredDocument) : null,
            'locales' => collect(RequiredDocument::LOCALES)
                ->mapWithKeys(fn (string $l) => [$l => [
                    'title' => $t[$l]['title'] ?? '',
                    'body' => $t[$l]['body'] ?? '',
                ]])->all(),
        ]);
    }

    /**
     * 본문 저장. bump_version=true 면 버전을 올려 기존 동의를 무효화한다
     * (문안이 실질적으로 바뀐 경우에만 — 오탈자 수정에는 올리지 않는다).
     */
    public function update(Request $request, RequiredDocument $requiredDocument): JsonResponse
    {
        $data = $request->validate([
            'locales' => ['required', 'array'],
            'locales.*.title' => ['nullable', 'string', 'max:200'],
            'locales.*.body' => ['nullable', 'string', 'max:100000'],
            'required' => ['boolean'],
            'active' => ['boolean'],
            'bump_version' => ['boolean'],
        ]);

        $translations = [];
        foreach (RequiredDocument::LOCALES as $locale) {
            $translations[$locale] = [
                'title' => trim((string) ($data['locales'][$locale]['title'] ?? '')),
                'body' => trim((string) ($data['locales'][$locale]['body'] ?? '')),
            ];
        }

        $active = (bool) ($data['active'] ?? $requiredDocument->active);

        // 읽을 것이 하나도 없는 문서를 켜면 빈 화면에 동의를 받게 된다.
        // 본문이 없어도 내려받을 원본이 붙어 있으면 읽을 수 있으므로 통과시킨다.
        if ($active && ! filled($translations['ko']['body']) && ! $requiredDocument->hasFile()) {
            return response()->json([
                'ok' => false,
                'message' => '한국어 본문을 입력하거나 원본 파일을 붙여야 사용으로 켤 수 있습니다.',
            ], 422);
        }

        $requiredDocument->translations = $translations;
        $requiredDocument->required = (bool) ($data['required'] ?? $requiredDocument->required);
        $requiredDocument->active = $active;

        if ($data['bump_version'] ?? false) {
            $requiredDocument->version++;
        }

        $requiredDocument->save();

        activity('required-document')
            ->performedOn($requiredDocument)
            ->causedBy(Auth::user())
            ->withProperties(['version' => $requiredDocument->version, 'active' => $requiredDocument->active])
            ->log('필수 문서 수정');

        return response()->json([
            'ok' => true,
            'message' => ($data['bump_version'] ?? false)
                ? "새 버전(v{$requiredDocument->version})으로 저장했습니다. 근로자에게 재동의를 받습니다."
                : '저장했습니다.',
            'rows' => self::rows(),
        ]);
    }
}
