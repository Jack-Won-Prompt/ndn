<?php

declare(strict_types=1);

namespace App\Domains\Support\Services;

use App\Domains\Support\Models\WorkerGuide;
use App\Domains\Support\Models\WorkerGuideSection;
use App\Models\Setting;
use App\Shared\Translation\GoogleTranslator;
use App\Shared\Translation\SiteTranslator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 근로자 안내 자료를 앱이 쓸 형태로 만든다.
 *
 * 하는 일 두 가지.
 *   1) 연락처처럼 관리자 설정에서 오는 값을 채워 넣기 ('@setting:키')
 *   2) 한국어 원문을 근로자 언어로 번역 (캐시)
 *
 * 회사소개 콘텐츠(SiteContentPresenter)와 같은 방식이다. 다만 이쪽은 로그인한
 * 근로자만 보고, 자료 한 편이 통째로 나가므로 목록과 본문을 나눠 담는다.
 */
class WorkerGuidePresenter
{
    /** 설정값을 끌어다 쓰는 자리 표시자. 예: '@setting:company.phone' */
    private const SETTING_PREFIX = '@setting:';

    /** 아직 값이 없는 설정 자리에 넣을 표시 (원본 서식의 밑줄 빈칸에 해당). */
    private const BLANK = '—';

    /**
     * 목록 — 제목·머리말만. 본문은 열 때 받는다.
     *
     * @return list<array<string, mixed>>
     */
    public function index(string $locale): array
    {
        $locale = $this->locale($locale);

        $guides = WorkerGuide::query()->active()->get();

        return $this->cached('worker_guides:index', $locale, $guides->max('updated_at')?->timestamp ?? 0,
            fn () => $this->translate($guides->map(fn (WorkerGuide $g) => [
                'key' => $g->key,
                'title' => $g->title,
                'lead' => $g->lead,
                'icon' => $g->icon,
            ])->all(), $locale));
    }

    /**
     * 자료 한 편 — 섹션 전문까지.
     *
     * @return array<string, mixed>
     */
    public function show(WorkerGuide $guide, string $locale): array
    {
        $locale = $this->locale($locale);

        $guide->loadMissing('sections');
        $stamp = max(
            $guide->updated_at?->timestamp ?? 0,
            $guide->sections->max('updated_at')?->timestamp ?? 0,
        );

        return $this->cached('worker_guide:'.$guide->key, $locale, $stamp, fn () => $this->translate([
            'key' => $guide->key,
            'title' => $guide->title,
            'lead' => $guide->lead,
            'icon' => $guide->icon,
            'sections' => $guide->sections->map(fn (WorkerGuideSection $s) => [
                'type' => $s->type,
                'payload' => $this->fillSettings((array) $s->payload),
            ])->all(),
        ], $locale));
    }

    /**
     * 내용이 바뀌면 캐시가 저절로 무효화되도록 최종 수정 시각을 키에 넣는다.
     *
     * 캐시가 죽어 있어도 안내 자료는 나와야 한다. 근로자가 보는 화면이라
     * 느려질지언정 빈 화면이나 오류를 주면 안 된다.
     */
    private function cached(string $prefix, string $locale, int $stamp, callable $build): array
    {
        $key = sprintf('%s:%s:%d', $prefix, $locale, $stamp);

        try {
            return Cache::remember($key, now()->addDay(), $build);
        } catch (Throwable $e) {
            Log::warning('[WorkerGuide] 캐시를 쓰지 못해 매번 새로 만듭니다: '.$e->getMessage());

            return $build();
        }
    }

    private function locale(string $locale): string
    {
        return SiteTranslator::isSupported($locale) ? $locale : 'ko';
    }

    /**
     * '@setting:키' 자리에 관리자 설정값을 넣는다.
     *
     * 원본 서식은 대표번호·비상연락망을 밑줄 빈칸으로 두고 인쇄해서 손으로 적게
     * 되어 있다. 앱에서는 콘솔 [사이트 설정]에 넣은 값이 그 자리에 들어간다.
     */
    private function fillSettings(array $payload): array
    {
        array_walk_recursive($payload, function (&$value) {
            if (is_string($value) && str_starts_with($value, self::SETTING_PREFIX)) {
                $value = Setting::get(substr($value, strlen(self::SETTING_PREFIX))) ?: self::BLANK;
            }
        });

        return $payload;
    }

    /**
     * 사람이 읽는 문자열만 골라 한 번에 번역한다.
     *
     * 값을 하나씩 번역하면 요청이 수백 번 나간다. 전부 모아 한 번에 보내고
     * 자리에 되돌려 놓는다(SiteContentPresenter 와 같다).
     */
    private function translate(array $data, string $locale): array
    {
        if ($locale === 'ko') {
            return $data;
        }

        $strings = [];
        $this->walk($data, function (string $value) use (&$strings) {
            $strings[$value] = true;

            return $value;
        });

        $source = array_keys($strings);
        if ($source === []) {
            return $data;
        }

        $translated = GoogleTranslator::translateLines($source, $locale, 'ko');
        $map = array_combine($source, $translated);

        return $this->walk($data, fn (string $v) => $map[$v] ?? $v);
    }

    /**
     * 번역 대상 문자열에만 콜백을 적용한다.
     *
     * 전화번호·아이콘 이름·키는 번역하면 안 된다. '112' 를 번역기에 넣으면
     * 다른 숫자로 바뀌어 나오는 일이 실제로 있다.
     */
    private function walk(array $node, callable $fn): array
    {
        static $skip = ['key', 'type', 'icon', 'value'];

        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $node[$k] = $this->walk($v, $fn);
            } elseif (is_string($v) && $v !== '' && ! in_array((string) $k, $skip, true)) {
                $node[$k] = $fn($v);
            }
        }

        return $node;
    }
}
