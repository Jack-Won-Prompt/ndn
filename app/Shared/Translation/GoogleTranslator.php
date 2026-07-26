<?php

declare(strict_types=1);

namespace App\Shared\Translation;

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
