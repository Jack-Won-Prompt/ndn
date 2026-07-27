<?php

declare(strict_types=1);

namespace App\Domains\Arrival\Actions;

use App\Domains\Arrival\Enums\ArrivalDocument;
use App\Domains\Arrival\Models\ArrivalRecord;
use App\Models\User;

/**
 * 입국 정보(항공편·픽업 배차)와 서류 체크리스트 갱신 (CLAUDE.md §4).
 *
 * 서류는 확인 여부(bool)만 저장한다. 파일은 온보딩 스토리지에만 둔다(§7-1).
 */
class UpdateArrivalDetailsAction
{
    /**
     * @param  array<string, mixed>  $attributes  flight_no·airport·scheduled_arrival_at·pickup_user_id·vehicle_no·note
     * @param  array<string, bool>|null  $documents  ArrivalDocument 키 → 확인 여부 (부분 갱신 가능)
     */
    public function execute(
        ArrivalRecord $record,
        array $attributes,
        ?array $documents,
        User $actor,
    ): ArrivalRecord {
        $record->fill(array_intersect_key($attributes, array_flip([
            'flight_no',
            'airport',
            'scheduled_arrival_at',
            'pickup_user_id',
            'vehicle_no',
            'note',
        ])));

        if ($documents !== null) {
            // 알 수 없는 키가 섞여 들어오지 않도록 Enum 키만 남긴다.
            $current = $record->checklist();

            foreach ($documents as $key => $checked) {
                if (in_array($key, ArrivalDocument::keys(), true)) {
                    $current[$key] = (bool) $checked;
                }
            }

            $record->documents = $current;
        }

        $record->save();

        activity('arrival')
            ->performedOn($record)
            ->causedBy($actor)
            ->log('입국 정보 수정');

        return $record->refresh();
    }
}
