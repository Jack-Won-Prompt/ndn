<?php

declare(strict_types=1);

namespace App\Shared\Translation;

use Illuminate\Support\Facades\Cache;

/**
 * 회사소개(마케팅) 페이지 자동 번역.
 *
 * 렌더된 HTML 의 텍스트 노드를 추출해 Google 번역(배치+영구 캐시)으로 치환한다.
 * 전체 페이지 결과도 (locale, 원본 해시)별로 영구 캐시하므로 최초 1회만 번역한다.
 * data-no-translate 요소(예: 언어 선택기의 자국어 표기)와 script/style 은 건드리지 않는다.
 */
class SiteTranslator
{
    /** 번역 지원 언어 — 한국어 원본 + 영어 + 근로자 4개국 */
    // 표시 순서 = 이 배열 순서. 한국어(원문) → 영어(심사자·외부 방문자) → 근로자 언어.
    public const LOCALES = ['ko', 'en', 'bn', 'lo', 'si', 'vi'];

    public const NATIVE = [
        'ko' => '한국어',
        'en' => 'English',
        'bn' => 'বাংলা',
        'lo' => 'ລາວ',
        'si' => 'සිංහල',
        'vi' => 'Tiếng Việt',
    ];

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::LOCALES, true);
    }

    /** 페이지 HTML 을 locale 로 번역(캐시). ko 는 원본 그대로. */
    public function translateHtml(string $html, string $locale): string
    {
        if ($locale === 'ko' || ! self::isSupported($locale)) {
            return $html;
        }

        $cacheKey = 'site_page:'.$locale.':'.md5($html);

        return Cache::rememberForever($cacheKey, fn () => $this->render($html, $locale));
    }

    private function render(string $html, string $locale): string
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        // UTF-8 보존을 위해 인코딩 힌트를 앞에 붙인다.
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        /** @var array<int, \DOMText> $nodes */
        $nodes = [];
        $originals = [];

        foreach ($xpath->query('//text()') as $node) {
            if (! $node instanceof \DOMText) {
                continue;
            }
            if ($this->skip($node)) {
                continue;
            }
            $core = trim($node->nodeValue);
            // 문자(letter)가 없는 노드(숫자·기호·공백)는 번역하지 않는다.
            if ($core === '' || ! preg_match('/\p{L}/u', $core)) {
                continue;
            }
            $nodes[] = $node;
            $originals[] = $core;
        }

        if (! $nodes) {
            return $html;
        }

        $translated = GoogleTranslator::translateLines($originals, $locale, 'ko');

        foreach ($nodes as $k => $node) {
            $raw = $node->nodeValue;
            $core = trim($raw);
            // 앞뒤 공백을 보존하고 가운데 텍스트만 치환한다.
            $lead = mb_substr($raw, 0, mb_strpos($raw, $core));
            $trail = mb_substr($raw, mb_strpos($raw, $core) + mb_strlen($core));
            $node->nodeValue = $lead.($translated[$k] ?? $core).$trail;
        }

        // html lang 속성도 갱신
        $htmlEl = $dom->getElementsByTagName('html')->item(0);
        if ($htmlEl instanceof \DOMElement) {
            $htmlEl->setAttribute('lang', $locale);
        }

        $out = $dom->saveHTML();

        // loadHTML 에 붙인 xml 선언 제거
        return (string) preg_replace('/<\?xml encoding="UTF-8"\?>\n?/', '', (string) $out);
    }

    /** script/style/no-translate 하위 노드는 건너뛴다. */
    private function skip(\DOMText $node): bool
    {
        for ($p = $node->parentNode; $p instanceof \DOMElement; $p = $p->parentNode) {
            $tag = strtolower($p->nodeName);
            if ($tag === 'script' || $tag === 'style') {
                return true;
            }
            if ($p->hasAttribute('data-no-translate')) {
                return true;
            }
        }

        return false;
    }
}
