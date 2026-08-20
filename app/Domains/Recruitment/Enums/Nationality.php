<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Enums;

/**
 * 송출국 (CLAUDE.md §6).
 *
 * 화면·DB 어디서나 **ISO 2자 코드**가 값이다. 매칭이 `demand_requests.nationality`
 * 와 대문자 코드로 대조하므로 표시 이름을 값으로 쓰면 안 된다.
 *
 * 이름은 세 가지로 낸다.
 *   - 한국어: 담당자 화면
 *   - 자국어(native): 근로자가 자기 나라를 고르는 자리. **번역기에 맡기지 않는다** —
 *     나라 이름은 기계 번역이 자주 틀리고, 자기 나라 이름을 못 알아보면 가입 자체가 막힌다
 *   - 코드: 목록·요약처럼 좁은 칸
 */
enum Nationality: string
{
    case VN = 'VN';
    case BD = 'BD';
    case LA = 'LA';
    case LK = 'LK';
    case NP = 'NP';
    case KG = 'KG';

    /** 담당자 화면용 한국어 이름. */
    public function label(): string
    {
        return match ($this) {
            self::VN => '베트남',
            self::BD => '방글라데시',
            self::LA => '라오스',
            self::LK => '스리랑카',
            self::NP => '네팔',
            self::KG => '키르기스스탄',
        };
    }

    /** 그 나라 말로 쓴 이름. 근로자가 자기 나라를 알아볼 수 있어야 한다. */
    public function native(): string
    {
        return match ($this) {
            self::VN => 'Việt Nam',
            self::BD => 'বাংলাদেশ',
            self::LA => 'ລາວ',
            self::LK => 'ශ්‍රී ලංකාව',
            self::NP => 'नेपाल',
            self::KG => 'Кыргызстан',
        };
    }

    /** 근로자가 쓰는 언어 코드 — 가입 화면에서 언어를 함께 맞춰 주는 데 쓴다. */
    public function locale(): string
    {
        return match ($this) {
            self::VN => 'vi',
            self::BD => 'bn',
            self::LA => 'lo',
            self::LK => 'si',
            self::NP => 'ne',
            self::KG => 'ky',
        };
    }

    /**
     * 근로자 화면용 선택지 — 한국어와 자국어를 함께 보여 준다.
     *
     * 둘 다 보여 주는 이유: 한국어만 있으면 근로자가 못 읽고, 자국어만 있으면
     * 옆에서 돕는 한국인 담당자가 못 읽는다. 현지 사무실에서 함께 앉아 채우는 화면이다.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $n) => [$n->value => $n->native().' · '.$n->label()])
            ->all();
    }

    /** 담당자 화면용 선택지 (한국어만). @return array<string, string> */
    public static function adminOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $n) => [$n->value => $n->label()])
            ->all();
    }

    /** 코드로 찾되 모르는 코드에도 죽지 않는다 — 옛 자료에 다른 코드가 섞여 있다. */
    public static function tryLabel(?string $code): string
    {
        if (blank($code)) {
            return '—';
        }

        return self::tryFrom(strtoupper($code))?->label() ?? strtoupper($code);
    }
}
