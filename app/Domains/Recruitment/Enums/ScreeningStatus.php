<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Enums;

/**
 * 가입 신청의 선발 진행 상태 (업무흐름 §2).
 *
 * 계정 상태(WorkerStatus)와 다르다. 그쪽은 "로그인이 되느냐"이고 이쪽은
 * "심사가 어디까지 갔느냐"다. 승인 대기 줄에 선 사람은 계정 상태가 전부
 * `pending` 이라, 이 값이 없으면 담당자가 이미 손댄 신청과 아직 안 본 신청을
 * 구분할 수 없다.
 */
enum ScreeningStatus: string
{
    case Received = 'received';                 // 접수 — 아직 아무도 보지 않음
    case SupplementRequested = 'supplement';    // 보완 요청 — 근로자 회신 대기
    case Held = 'held';                         // 보류 — 판단 미룸
    case Passed = 'passed';                     // 합격 — 계정도 함께 활성화된다
    case Failed = 'failed';                     // 불합격

    public function label(): string
    {
        return match ($this) {
            self::Received => '접수',
            self::SupplementRequested => '보완 요청',
            self::Held => '보류',
            self::Passed => '합격',
            self::Failed => '불합격',
        };
    }

    /** 담당자가 아직 손대야 하는 건 — 사이드바 배지의 기준. */
    public function isOpen(): bool
    {
        return match ($this) {
            self::Received, self::SupplementRequested, self::Held => true,
            self::Passed, self::Failed => false,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Received => 'info',
            self::SupplementRequested, self::Held => 'warn',
            self::Passed => 'done',
            self::Failed => 'bad',
        };
    }

    /** 담당자가 내릴 수 있는 결정 — 접수·보완·보류 어디서든 같다. */
    public static function decisions(): array
    {
        return [self::SupplementRequested, self::Passed, self::Held, self::Failed];
    }
}
