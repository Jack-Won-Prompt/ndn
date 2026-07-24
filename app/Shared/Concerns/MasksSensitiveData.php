<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

/**
 * 민감 필드가 로그·예외·배열 변환 시 그대로 노출되지 않도록 마스킹한다 (CLAUDE.md §7-1).
 *
 * 이 trait 을 쓰는 모델은 $sensitive 프로퍼티에 가릴 속성명을 나열한다.
 * toArray() 결과와 __toString() 에서 해당 값들이 마스킹된다.
 *
 *   protected array $sensitive = ['passport_no', 'birth_date', 'phone_home_country'];
 */
trait MasksSensitiveData
{
    /**
     * 민감 값을 앞 1글자만 남기고 가린다. (예: "M12345678" → "M••••••••")
     * 로그·디버그 출력에서 원문이 드러나지 않는 것이 목적이며, 복호화된 값을
     * 배열로 흘리지 않게 한다.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        foreach ($this->sensitiveAttributes() as $key) {
            if (array_key_exists($key, $array) && filled($array[$key])) {
                $array[$key] = self::maskValue((string) $array[$key]);
            }
        }

        return $array;
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    /** @return array<int, string> */
    protected function sensitiveAttributes(): array
    {
        return property_exists($this, 'sensitive') ? $this->sensitive : [];
    }

    protected static function maskValue(string $value): string
    {
        $len = mb_strlen($value);

        if ($len <= 1) {
            return str_repeat('•', max($len, 1));
        }

        return mb_substr($value, 0, 1).str_repeat('•', $len - 1);
    }
}
