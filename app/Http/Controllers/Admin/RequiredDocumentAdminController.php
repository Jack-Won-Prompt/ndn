<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Onboarding\Models\RequiredDocument;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
                'updated' => LocalTime::format($d->updated_at),
            ])->all();
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
