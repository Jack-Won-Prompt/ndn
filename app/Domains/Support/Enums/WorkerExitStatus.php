<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

/**
 * 조기 귀국·이탈 건의 진행 상태 (업무흐름 §8).
 *
 * 상태 집합이 유형마다 다르다. 조기 귀국에 '이탈 확정'이 있을 수 없고,
 * 이탈에 '반려'가 있을 수 없다. 그래서 전이표가 유형을 함께 받는다 —
 * 유형을 무시하면 화면에서 있을 수 없는 버튼이 뜬다.
 */
enum WorkerExitStatus: string
{
    // 조기 귀국
    case Requested = 'requested';   // 신청 접수 (앱 민원 또는 본사 등록)
    case Approved = 'approved';     // 귀국 승인 — 항공·인계 준비
    case Rejected = 'rejected';     // 반려 — 계속 근무
    case Completed = 'completed';   // 출국 완료

    // 이탈·연락두절
    case Unreachable = 'unreachable'; // 연락두절 — 찾는 중
    case Confirmed = 'confirmed';     // 이탈 확정 — 신고 대상
    case Recovered = 'recovered';     // 소재 확인·복귀

    public function label(): string
    {
        return match ($this) {
            self::Requested => '신청 접수',
            self::Approved => '귀국 승인',
            self::Rejected => '반려',
            self::Completed => '출국 완료',
            self::Unreachable => '연락두절',
            self::Confirmed => '이탈 확정',
            self::Recovered => '소재 확인·복귀',
        };
    }

    /**
     * 이 상태에서 갈 수 있는 다음 상태.
     *
     * 이탈 확정에서 '소재 확인'으로 되돌아가는 길을 남겨 둔다. 확정한 뒤에
     * 본인이 나타나는 일이 실제로 있고, 그때 기록을 고칠 수 없으면 담당자가
     * 새 건을 또 만든다.
     *
     * @return list<self>
     */
    public function allowedTransitions(WorkerExitType $type): array
    {
        return match ($type) {
            WorkerExitType::EarlyReturn => match ($this) {
                self::Requested => [self::Approved, self::Rejected],
                self::Approved => [self::Completed],
                default => [],
            },
            WorkerExitType::Absconded => match ($this) {
                self::Unreachable => [self::Confirmed, self::Recovered],
                self::Confirmed => [self::Recovered],
                default => [],
            },
        };
    }

    public function canTransitionTo(self $target, WorkerExitType $type): bool
    {
        return in_array($target, $this->allowedTransitions($type), true);
    }

    /** 아직 담당자가 손대야 하는 건인가 — 사이드바 배지와 '진행 중' 목록의 기준. */
    public function isOpen(): bool
    {
        return match ($this) {
            self::Requested, self::Approved, self::Unreachable, self::Confirmed => true,
            self::Rejected, self::Completed, self::Recovered => false,
        };
    }

    /** 화면 배지 색 구분. */
    public function tone(): string
    {
        return match ($this) {
            self::Requested, self::Unreachable => 'warn',
            self::Approved => 'info',
            self::Confirmed => 'bad',
            self::Completed, self::Recovered, self::Rejected => 'done',
        };
    }
}
