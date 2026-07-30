<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Shared\Translation\SiteTranslator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * 회사소개(마케팅) 사이트 — 선택 언어로 자동 번역해 렌더한다.
 *
 * 기본은 한국어(원본). 방문자가 언어 선택기로 bn/lo/si/vi 를 고르면 세션에 저장하고
 * 렌더된 HTML 을 SiteTranslator 로 번역(캐시)해 반환한다.
 */
class SiteController extends Controller
{
    /** key => [view, active] */
    private const PAGES = [
        'home' => ['site.home', 'home'],
        'about' => ['site.about', 'about'],
        'services' => ['site.services', 'services'],
        'worker' => ['site.worker', 'worker'],
        'partners' => ['site.partners', 'partners'],
        'contact' => ['site.contact', 'contact'],
        // 법적 고지 페이지 (플레이스토어 제출용 — 공개·비로그인·자동번역)
        'privacy' => ['site.privacy', ''],
        'terms' => ['site.terms', ''],
    ];

    public function page(Request $request, SiteTranslator $translator): Response
    {
        $key = (string) $request->route('key');
        abort_unless(isset(self::PAGES[$key]), 404);

        [$view, $active] = self::PAGES[$key];
        $html = view($view, ['active' => $active])->render();

        $locale = (string) $request->session()->get('site_locale', 'ko');

        return response($translator->translateHtml($html, $locale));
    }

    /** 사이트 표시 언어 전환 → 세션 저장 후 이전 페이지로. */
    public function setLocale(Request $request, string $locale): RedirectResponse
    {
        if (SiteTranslator::isSupported($locale)) {
            $request->session()->put('site_locale', $locale);
        }

        return redirect()->to($this->backTarget($request));
    }

    /**
     * 언어 전환 후 돌아갈 주소.
     *
     * redirect()->back() 을 그대로 쓰면 안 된다. 세션이 기억하는 '이전 주소'가
     * 이 언어 전환 주소 자신일 때가 있는데(직전 요청도 GET 이라 기록된다),
     * 그러면 자기에게 무한히 돌아가 브라우저가 ERR_TOO_MANY_REDIRECTS 를 낸다.
     *
     * 같은 사이트 안의 주소이면서 언어 전환 경로가 아닐 때만 돌아간다.
     */
    private function backTarget(Request $request): string
    {
        $previous = (string) url()->previous();

        if ($previous === '' || ! str_starts_with($previous, $request->getSchemeAndHttpHost())) {
            return route('site.home');
        }

        $path = (string) parse_url($previous, PHP_URL_PATH);

        return str_contains($path, '/set-language/') ? route('site.home') : $previous;
    }
}
