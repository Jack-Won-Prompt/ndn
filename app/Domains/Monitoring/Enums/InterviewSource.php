<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Enums;

/**
 * 근로 생활 평가의 작성 주체 (업무흐름 §7).
 *
 * - Inspector: 점검자가 농가 방문 시 근로자와 진행한 월별 점검
 * - Self: 근로자가 앱에서 직접 제출한 자가 평가
 *
 * 자가 평가는 점검자 방문 사이의 공백을 메우는 조기 신호로 쓰인다.
 */
enum InterviewSource: string
{
    case Inspector = 'inspector';
    case Self = 'self';

    public function label(): string
    {
        return match ($this) {
            self::Inspector => '점검자 방문',
            self::Self => '근로자 자가 평가',
        };
    }
}
