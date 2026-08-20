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
     * 근로자의 locale 은 **기본값**이다 — 메일 링크로 들어온 사람은 헤더를
     * 건드리지 않아도 자기 말로 봐야 한다.
     *
     * 다만 헤더의 언어 선택기를 **직접 누른 경우에는 그쪽이 이긴다.** 눌렀는데
     * 화면이 그대로면 선택기가 고장 난 것으로 보이고, 옆에서 돕는 담당자가
     * 한국어로 바꿔 함께 보는 일도 실제로 있다.
     *
     * 기본값 대신 has() 로 본다 — session('site_locale', 'ko') 는 고른 적이
     * 없어도 'ko' 를 돌려주므로, 그걸로는 '누른 것' 과 '안 누른 것' 을 구분할 수 없다.
     */
    protected function displayLocale(?Worker $worker = null): string
    {
        $picked = session('site_locale');

        if (filled($picked) && SiteTranslator::isSupported((string) $picked)) {
            return (string) $picked;
        }

        if ($worker !== null && filled($worker->locale) && SiteTranslator::isSupported($worker->locale)) {
            return $worker->locale;
        }

        return 'ko';
    }
}
