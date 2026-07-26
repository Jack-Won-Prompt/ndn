<?php

declare(strict_types=1);

namespace App\Shared\Translation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 무료 Google Translate 엔드포인트 기반 번역기 (API 키 불필요).
 * supportworks 의 TranslateController::googleTranslate 를 NDN 용으로 가져옴.
 * 실패 시 원문을 그대로 반환(폴백)한다.
 */
class GoogleTranslator
{
    /** 지원 언어(근로자 송출국 + 한국어) */
    public const LANGS = ['ko', 'bn', 'lo', 'si', 'vi', 'en', 'zh'];

    /**
     * text 를 target 언어로 번역. from 은 'auto' 가능.
     * 같은 언어이거나 빈 문자열이면 원문 반환.
     */
    public static function translate(string $text, string $target, string $from = 'auto'): string
    {
        $text = trim($text);
        if ($text === '' || $target === $from) {
            return $text;
        }

        try {
            $chunks = self::splitIntoChunks($text, 3000);
            $out = [];
            foreach ($chunks as $chunk) {
                $out[] = self::chunk($chunk, $target, $from);
            }
            $result = trim(implode("\n", $out));

            return $result !== '' ? $result : $text;
        } catch (\Throwable $e) {
            Log::warning('[GoogleTranslator] 실패, 원문 반환: '.$e->getMessage());

            return $text;   // 폴백: 원문
        }
    }

    /**
     * 여러 짧은 문자열을 한 번(또는 몇 번)의 호출로 번역한다 (줄바꿈 배치).
     * 문자열별로 영구 캐시하며, 배치 줄수 불일치 시 개별 번역으로 폴백한다.
     * 사이트 페이지처럼 텍스트 노드가 많은 경우 호출 수를 크게 줄인다.
     *
     * @param  array<int, string>  $lines
     * @return array<int, string> 입력과 같은 키로 정렬된 번역 결과
     */
    public static function translateLines(array $lines, string $target, string $from = 'auto'): array
    {
        $result = [];
        $need = [];   // index => 정규화된 원문(캐시 미스)

        foreach ($lines as $i => $line) {
            $norm = trim((string) preg_replace('/\s+/u', ' ', $line));
            if ($norm === '' || $target === $from) {
                $result[$i] = $line;

                continue;
            }
            $cached = Cache::get(self::cacheKey($norm, $target));
            if ($cached !== null) {
                $result[$i] = $cached;
            } else {
                $need[$i] = $norm;
            }
        }

        // 캐시 미스만 ~2500자 단위로 묶어 번역
        $batch = [];
        $len = 0;
        $flush = function () use (&$batch, &$len, &$need, &$result, $target, $from) {
            if (! $batch) {
                return;
            }
            $joined = implode("\n", array_map(fn ($i) => $need[$i], $batch));
            try {
                $out = explode("\n", self::chunk($joined, $target, $from));
            } catch (\Throwable $e) {
                $out = [];
            }

            if (count($out) === count($batch)) {
                foreach (array_values($batch) as $k => $i) {
                    $t = trim($out[$k]);
                    $result[$i] = $t !== '' ? $t : $need[$i];
                    Cache::forever(self::cacheKey($need[$i], $target), $result[$i]);
                }
            } else {
                // 줄수 불일치 → 개별 번역 폴백
                foreach ($batch as $i) {
                    $result[$i] = self::translate($need[$i], $target, $from);
                    Cache::forever(self::cacheKey($need[$i], $target), $result[$i]);
                }
            }
            $batch = [];
            $len = 0;
        };

        foreach (array_keys($need) as $i) {
            $l = mb_strlen($need[$i]);
            if ($batch && $len + $l > 2500) {
                $flush();
            }
            $batch[] = $i;
            $len += $l + 1;
        }
        $flush();

        ksort($result);

        return $result;
    }

    private static function cacheKey(string $text, string $target): string
    {
        return 'gt:'.$target.':'.md5($text);
    }

    private static function chunk(string $text, string $target, string $from): string
    {
        $url = 'https://translate.googleapis.com/translate_a/single?'
            .http_build_query(['client' => 'gtx', 'sl' => $from, 'tl' => $target, 'dt' => 't']);

        $res = Http::withOptions(['verify' => false])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->timeout(15)
            ->asForm()
            ->post($url, ['q' => $text]);

        if (! $res->successful()) {
            throw new \RuntimeException('HTTP '.$res->status());
        }

        $data = $res->json();

        return collect($data[0] ?? [])->pluck(0)->filter()->implode('');
    }

    private static function splitIntoChunks(string $text, int $maxLen): array
    {
        if (mb_strlen($text) <= $maxLen) {
            return [$text];
        }
        $chunks = [];
        $current = '';
        foreach (preg_split('/(\n\n+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
            if (mb_strlen($current) + mb_strlen($part) > $maxLen && $current !== '') {
                $chunks[] = $current;
                $current = $part;
            } else {
                $current .= $part;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
