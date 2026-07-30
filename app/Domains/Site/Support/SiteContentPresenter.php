<?php

declare(strict_types=1);

namespace App\Domains\Site\Support;

use App\Domains\Site\Models\SitePage;
use App\Models\Setting;
use App\Shared\Translation\GoogleTranslator;
use App\Shared\Translation\SiteTranslator;
use Illuminate\Support\Facades\Cache;

/**
 * 회사소개 콘텐츠를 앱이 쓸 형태로 만든다.
 *
 * 하는 일 세 가지.
 *   1) 이미지 경로를 절대 URL 로 (앱은 상대 경로를 풀 수 없다)
 *   2) 통계 숫자처럼 관리자 설정에서 오는 값을 채워 넣기
 *   3) 한국어 원문을 요청 언어로 번역 (캐시)
 *
 * 번역은 사이트 HTML 번역과 같은 방식이되, 여기서는 문자열만 골라 보낸다.
 * HTML 을 통째로 넘기면 태그까지 번역돼 깨진다.
 */
class SiteContentPresenter
{
    /**
     * @return list<array<string, mixed>>
     */
    public function pages(string $locale): array
    {
        $locale = SiteTranslator::isSupported($locale) ? $locale : 'ko';

        $pages = SitePage::query()
            ->where('in_app_nav', true)
            ->with('sections')
            ->orderBy('position')
            ->get();

        // 콘텐츠가 바뀌면 캐시가 저절로 무효화되도록 최종 수정 시각을 키에 넣는다.
        $stamp = $pages->max('updated_at')?->timestamp ?? 0;
        $sectionStamp = $pages->flatMap->sections->max('updated_at')?->timestamp ?? 0;
        $key = sprintf('site_content:%s:%d', $locale, max($stamp, $sectionStamp));

        return Cache::remember($key, now()->addDay(), function () use ($pages, $locale) {
            $data = $pages->map(fn (SitePage $page) => [
                'key' => $page->key,
                'title' => $page->title,
                'lead' => $page->lead,
                'icon' => $page->icon,
                'hero_image' => $this->url($page->hero_image),
                'sections' => $page->sections->map(fn ($s) => [
                    'type' => $s->type,
                    'payload' => $this->prepare($s->type, (array) $s->payload),
                ])->all(),
            ])->all();

            return $locale === 'ko' ? $data : $this->translate($data, $locale);
        });
    }

    /** 유형별 후처리 — 이미지 URL, 설정값 주입. */
    private function prepare(string $type, array $payload): array
    {
        if (isset($payload['image'])) {
            $payload['image'] = $this->url($payload['image']);
        }

        // 통계는 관리자가 설정에서 관리한다. 시더에는 설정 키만 들어 있다.
        if ($type === 'stats') {
            $payload['items'] = array_map(function (array $item) {
                $item['value'] = (string) (Setting::get($item['title'] ?? '') ?: '—');
                unset($item['title']);

                return $item;
            }, $payload['items'] ?? []);
        }

        return $payload;
    }

    private function url(?string $path): ?string
    {
        return $path === null || $path === '' ? null : asset($path);
    }

    /**
     * 사람이 읽는 문자열만 골라 한 번에 번역한다.
     *
     * 값을 하나씩 번역하면 요청이 수백 번 나간다. 전부 모아 한 번에 보내고
     * 자리에 되돌려 놓는다.
     */
    private function translate(array $data, string $locale): array
    {
        $strings = [];
        $this->walk($data, function (string $value) use (&$strings) {
            $strings[$value] = true;

            return $value;
        });

        $source = array_keys($strings);
        if ($source === []) {
            return $data;
        }

        // 문자열별 영구 캐시 + 배치 호출은 GoogleTranslator 가 이미 한다.
        $translated = GoogleTranslator::translateLines($source, $locale, 'ko');
        $map = array_combine($source, $translated);

        return $this->walk($data, fn (string $v) => $map[$v] ?? $v);
    }

    /**
     * 번역 대상 문자열에만 콜백을 적용한다.
     *
     * 이미지 URL·아이콘 이름·설정 키는 번역하면 안 된다. 값이 URL 이거나
     * 사람이 읽는 문장이 아닌 자리는 건너뛴다.
     */
    private function walk(array $node, callable $fn, string $key = ''): array
    {
        static $skip = ['image', 'hero_image', 'icon', 'key', 'type', 'value'];

        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $node[$k] = $this->walk($v, $fn, is_string($k) ? $k : $key);
            } elseif (is_string($v) && $v !== '' && ! in_array((string) $k, $skip, true)) {
                $node[$k] = $fn($v);
            }
        }

        return $node;
    }
}
