<?php

declare(strict_types=1);

namespace App\Shared\Translation\Concerns;

use App\Domains\Recruitment\Models\Worker;
use App\Shared\Translation\SiteTranslator;
use Illuminate\Http\Response;

/**
 * 근로자 대상 웹 화면을 그 사람의 언어로 렌더한다 (CLAUDE.md §6).
 *
 * 회사소개 사이트는 SiteController 가 세션 언어로 번역해 내보내는데, 근로자
 * 화면(지원·보완·로그인·본인 정보)은 그 컨트롤러를 타지 않아 **한국어 그대로**
 * 나갔다. 한국어를 못 읽는 사람이 쓰는 화면인데도.
 *
 * 어느 언어로 보여 줄지는 두 갈래다.
 *   - 누구인지 아는 화면(본인 화면·보완 링크) → **그 근로자가 고른 언어**.
 *     가입할 때 고른 언어로 알림을 받는 사람이니 화면도 같아야 한다.
 *   - 아직 모르는 화면(지원하기·로그인·비밀번호 찾기) → 방문자가 헤더에서 고른 언어.
 */
trait RendersInWorkerLocale
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderLocalized(string $view, array $data = [], ?Worker $worker = null): Response
    {
        $html = view($view, $data)->render();

        return response(
            app(SiteTranslator::class)->translateHtml($html, $this->displayLocale($worker))
        );
    }

    /**
     * 화면을 보여 줄 언어.
     *
     * 근로자를 아는 경우 그 사람의 locale 이 우선이다 — 헤더의 언어 선택기를
     * 건드리지 않아도 자기 말로 보여야 한다. 선택기로 바꾸면 세션 값이 이기게
     * 두지 않는다: 잘못 눌러 못 읽는 언어로 갇히는 쪽이 더 나쁘다.
     */
    protected function displayLocale(?Worker $worker = null): string
    {
        if ($worker !== null && filled($worker->locale) && SiteTranslator::isSupported($worker->locale)) {
            return $worker->locale;
        }

        $session = (string) session('site_locale', 'ko');

        return SiteTranslator::isSupported($session) ? $session : 'ko';
    }
}
