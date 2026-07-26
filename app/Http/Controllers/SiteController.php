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

        return redirect()->back(fallback: route('site.home'));
    }
}
