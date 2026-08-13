<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

/**
 * 조기 귀국·이탈 사유 (업무흐름 §8).
 *
 * 지자체·출입국 보고에 "왜 빠졌는지"가 반드시 들어가므로 자유 서술만 두면
 * 집계가 안 된다. 그래서 골라 담는 항목을 두고, 구체적인 사정은
 * `reason_detail` 에 따로 적는다.
 *
 * `Unknown` 을 남겨 둔 이유: 이탈은 인지한 순간 사유를 모른다. 억지로 하나를
 * 고르게 하면 그 값이 그대로 통계에 섞인다.
 */
enum WorkerExitReason: string
{
    case Personal = 'personal';       // 개인 사정
    case Illness = 'illness';         // 질환·부상
    case Family = 'family';           // 가족 사유 (본국 경조사 등)
    case FarmIssue = 'farm_issue';    // 농가 사정 (수요 축소·폐업)
    case Conflict = 'conflict';       // 갈등·부적응
    case Misconduct = 'misconduct';   // 본인 귀책 (규정 위반)
    case Unknown = 'unknown';         // 미상 — 아직 확인되지 않음
    case Other = 'other';             // 기타

    public function label(): string
    {
        return match ($this) {
            self::Personal => '개인 사정',
            self::Illness => '질환·부상',
            self::Family => '가족 사유',
            self::FarmIssue => '농가 사정',
            self::Conflict => '갈등·부적응',
            self::Misconduct => '본인 귀책',
            self::Unknown => '미상',
            self::Other => '기타',
        };
    }

    /**
     * 질환은 건강 정보라 민감정보에 가깝다. 통계에는 넣되 외부로 나가는
     * 문서·알림에 상세를 싣지 않는다는 표시로 쓴다(§7-3).
     */
    public function isHealthRelated(): bool
    {
        return $this === self::Illness;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
