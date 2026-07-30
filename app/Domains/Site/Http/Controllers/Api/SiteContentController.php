<?php

declare(strict_types=1);

namespace App\Domains\Site\Http\Controllers\Api;

use App\Domains\Site\Support\SiteContentPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 회사소개 콘텐츠 (모바일 앱 네이티브 화면).
 *
 * **인증 밖**이다. 로그인 전 첫 화면이 이 내용을 그리므로, 계정이 없는 사람도
 * 받을 수 있어야 한다. 공개 사이트에 이미 있는 내용이라 숨길 것도 없다.
 */
class SiteContentController extends Controller
{
    public function __invoke(Request $request, SiteContentPresenter $presenter): JsonResponse
    {
        $locale = (string) $request->query('locale', 'ko');

        return response()->json([
            'data' => $presenter->pages($locale),
            'meta' => ['locale' => $locale],
        ]);
    }
}
