<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Controllers\Api;

use App\Domains\Recruitment\Models\WorkerFile;
use App\Domains\Recruitment\Support\ApplicationDocuments;
use App\Http\Controllers\Controller;
use App\Shared\Translation\SiteTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 가입 화면이 물어볼 서류 안내 (근로자 앱).
 *
 * 가입은 토큰 발급 전이라 인증 밖이다. 개인정보가 섞이지 않는다 — 무엇을
 * 준비해 오라는 안내와 파일 제한뿐이다(§7).
 *
 * **앱에 목록을 박아 두지 않는 이유**: 받는 서류가 바뀌면 앱을 다시 배포하고
 * 스토어 심사를 기다려야 한다. 그 사이 웹(`/apply`)과 앱이 서로 다른 서류를
 * 안내하게 된다.
 *
 * 제한값(개수·크기·형식)도 함께 준다. 앱이 따로 들고 있으면 서버 규칙이 바뀔
 * 때 앱만 낡아, 사용자는 다 고른 뒤에야 거절당한다.
 */
class SignupDocumentController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // 아직 누구인지 모른다 — 앱이 고른 언어를 그대로 받는다.
        $locale = (string) $request->query('locale', 'ko');
        if (! SiteTranslator::isSupported($locale)) {
            $locale = 'ko';
        }

        return response()->json([
            'data' => [
                'expected' => ApplicationDocuments::expected($locale),
                'hint' => trans('worker.documents_hint', [], $locale),
            ],
            'meta' => [
                'max_files' => ApplicationDocuments::MAX_FILES,
                'max_kb' => WorkerFile::MAX_KB,
                'mimes' => explode(',', WorkerFile::MIMES),
                'locale' => $locale,
                // 서류가 없어도 접수된다는 것을 앱이 화면에서 분명히 하도록.
                'required' => false,
            ],
        ]);
    }
}
