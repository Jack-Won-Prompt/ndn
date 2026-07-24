<?php

declare(strict_types=1);

namespace App\Domains\Demand\Actions;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;

/**
 * 농가 수요 신청 생성 (CLAUDE.md §4: 비즈니스 로직은 단일 execute() Action 에).
 */
class CreateDemandRequestAction
{
    /**
     * @param  array<string, mixed>  $data  검증이 끝난 입력값 (FormRequest 통과분)
     */
    public function execute(Farm $farm, array $data): DemandRequest
    {
        return DemandRequest::create([
            'farm_id' => $farm->id,
            'city_id' => $data['city_id'] ?? $farm->city_id,
            'nationality' => $data['nationality'],
            'headcount' => $data['headcount'],
            'age_min' => $data['age_min'] ?? null,
            'age_max' => $data['age_max'] ?? null,
            'gender' => $data['gender'],
            'allow_siblings' => $data['allow_siblings'] ?? false,
            'crop' => $data['crop'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'note' => $data['note'] ?? null,
            'status' => DemandStatus::Draft,
        ]);
    }
}
