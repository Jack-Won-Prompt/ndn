<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

/**
 * 근로자가 계약 기간을 채우지 못하고 빠지는 두 가지 사건 (업무흐름 §8).
 *
 * 둘은 성격이 다르다.
 *   - 조기 귀국: **결정**이다. 신청을 받거나 본사가 판단해 승인/반려하고, 출국으로 끝난다.
 *   - 이탈·연락두절: **상태**다. 연락이 끊긴 채로 시간이 흐르고, 확정되거나 소재가 확인된다.
 *
 * 한 표에 담는 이유는 현장에서 두 사건이 이어지기 때문이다. 연락두절로 시작해
 * 소재가 확인되면 조기 귀국으로 정리되는 일이 흔하다. 나눠 두면 그 연결이 끊긴다.
 */
enum WorkerExitType: string
{
    case EarlyReturn = 'early_return';  // 조기 귀국
    case Absconded = 'absconded';       // 이탈·연락두절

    public function label(): string
    {
        return match ($this) {
            self::EarlyReturn => '조기 귀국',
            self::Absconded => '이탈·연락두절',
        };
    }

    /** 사건을 열었을 때의 시작 상태. */
    public function initialStatus(): WorkerExitStatus
    {
        return match ($this) {
            self::EarlyReturn => WorkerExitStatus::Requested,
            self::Absconded => WorkerExitStatus::Unreachable,
        };
    }

    /**
     * `occurred_on` 이 무엇을 가리키는지 — 유형마다 뜻이 다르다.
     * 화면 라벨을 여기서 정해 두어야 담당자가 엉뚱한 날짜를 넣지 않는다.
     */
    public function occurredLabel(): string
    {
        return match ($this) {
            self::EarlyReturn => '신청일',
            self::Absconded => '마지막 연락일',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
