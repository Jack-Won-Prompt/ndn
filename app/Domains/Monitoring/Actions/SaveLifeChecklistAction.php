<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Actions;

use App\Domains\Monitoring\Models\LifeChecklistCheck;
use App\Domains\Monitoring\Models\LifeChecklistItem;
use App\Domains\Recruitment\Models\Worker;
use Illuminate\Support\Facades\DB;

/**
 * 한국 생활 체크리스트 저장 (업무흐름 §7).
 *
 * 앱이 체크된 항목 전체를 보낸다. 부분 갱신이 아니라 통째로 맞춘다 —
 * 근로자가 비행기·농장에서 통신이 끊긴 채 여러 개를 체크했다가 나중에 한 번에
 * 올리는 상황을 그대로 받아낼 수 있고, 같은 요청을 두 번 보내도 결과가 같다.
 */
class SaveLifeChecklistAction
{
    /**
     * @param  list<int>  $checkedItemIds  체크된 항목 id 전체
     * @return int 저장 후 체크된 항목 수
     */
    public function execute(Worker $worker, array $checkedItemIds): int
    {
        // 꺼진 항목은 화면에 없으므로 요청에도 들어올 수 없다. 걸러 낸다.
        $valid = LifeChecklistItem::query()->active()
            ->whereIn('id', $checkedItemIds)
            ->pluck('id');

        return DB::transaction(function () use ($worker, $valid) {
            $existing = LifeChecklistCheck::query()
                ->where('worker_id', $worker->id)
                ->pluck('life_checklist_item_id')
                ->all();

            $add = $valid->diff($existing);
            $remove = array_diff($existing, $valid->all());

            if ($remove !== []) {
                LifeChecklistCheck::query()
                    ->where('worker_id', $worker->id)
                    ->whereIn('life_checklist_item_id', $remove)
                    ->delete();
            }

            foreach ($add as $itemId) {
                LifeChecklistCheck::create([
                    'worker_id' => $worker->id,
                    'life_checklist_item_id' => $itemId,
                    'checked_at' => now(),
                ]);
            }

            return $valid->count();
        });
    }
}
