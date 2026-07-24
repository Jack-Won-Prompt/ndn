<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Facades\Config;

/**
 * 암호화 컬럼(여권번호 등)을 검색 가능하게 하는 blind index 해시 (CLAUDE.md §7-1).
 *
 * 암호화 값은 매번 다른 ciphertext 라 WHERE 로 찾을 수 없다. 대신 정규화한 평문을
 * HMAC-SHA256 으로 해시한 결정적 값을 별도 컬럼(*_bidx)에 저장하고 그 컬럼으로 조회한다.
 *
 * 예)  $worker->passport_no_bidx = BlindIndex::hash($plainPassport);
 *      Worker::where('passport_no_bidx', BlindIndex::hash($query))->first();
 */
final class BlindIndex
{
    /**
     * 결정적 해시. 같은 평문 → 항상 같은 해시.
     *
     * 대소문자·공백 차이로 조회가 어긋나지 않도록 정규화(trim + 소문자)한 뒤 해시한다.
     */
    public static function hash(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        return hash_hmac('sha256', $normalized, self::key());
    }

    /**
     * blind index 전용 키. APP_KEY 를 그대로 쓰지 않고 도메인 분리를 위해 파생시킨다.
     * (APP_KEY 유출 시에도 blind index 키가 곧바로 노출되지 않도록)
     */
    private static function key(): string
    {
        $appKey = (string) Config::get('app.key');

        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7), true) ?: $appKey;
        }

        return hash_hmac('sha256', 'blind-index', $appKey);
    }
}
