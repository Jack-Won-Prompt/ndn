<?php

declare(strict_types=1);

namespace App\Domains\Demand\Enums;

/**
 * 농가 수요 신청(DemandRequest)의 상태 (CLAUDE.md §5).
 *
 * 흐름: draft → submitted(농가 제출) → aggregated(시청 취합)
 *        → letter_issued(Demand Letter 발행) 또는 rejected(반려)
 */
enum DemandStatus: string
{
    case Draft = 'draft';         // 농가 작성 중
    case Submitted = 'submitted';     // 농가 제출, 시청 검토 대기
    case Aggregated = 'aggregated';    // 시청이 취합 완료
    case LetterIssued = 'letter_issued'; // Demand Letter 발행됨
    case Rejected = 'rejected';      // 반려

    public function label(): string
    {
        return match ($this) {
            self::Draft => '작성 중',
            self::Submitted => '제출됨',
            self::Aggregated => '취합 완료',
            self::LetterIssued => 'Demand Letter 발행',
            self::Rejected => '반려',
        };
    }

    /**
     * 농가가 아직 수정할 수 있는 상태인지. (draft 만 편집 가능)
     */
    public function isEditableByFarm(): bool
    {
        return $this === self::Draft;
    }

    /**
     * 현재 상태에서 넘어갈 수 있는 다음 상태들.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::Aggregated, self::Rejected],
            self::Aggregated => [self::LetterIssued, self::Rejected],
            self::LetterIssued => [],
            self::Rejected => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
