<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Http\Controllers\Api;

use App\Domains\Monitoring\Actions\SaveLifeChecklistAction;
use App\Domains\Monitoring\Http\Requests\StoreLifeChecklistRequest;
use App\Domains\Monitoring\Models\LifeChecklistCheck;
use App\Domains\Monitoring\Models\LifeChecklistItem;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use App\Shared\Translation\GoogleTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * 근로자 앱 — 한국 생활 체크리스트 (입국 후 1주일 이내 확인사항).
 *
 * 근로자가 스스로 확인하며 체크한다. 관리자는 아직 확인하지 않은 항목을 보고
 * 챙긴다. 월별 자가 평가(6항목)를 대신하는 화면이다.
 */
class LifeChecklistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $items = LifeChecklistItem::query()->active()->get();
        $checked = $this->checkedIds($worker);

        return response()->json([
            'data' => $this->rows($items, $checked, $worker->locale ?: 'ko'),
            'meta' => [
                'locale' => $worker->locale ?: 'ko',
                'total' => $items->count(),
                'checked_count' => $items->whereIn('id', $checked)->count(),
            ],
        ]);
    }

    public function store(
        StoreLifeChecklistRequest $request,
        SaveLifeChecklistAction $action,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $action->execute($worker, $request->checkedIds());

        $items = LifeChecklistItem::query()->active()->get();
        $checked = $this->checkedIds($worker);

        return response()->json([
            'data' => $this->rows($items, $checked, $worker->locale ?: 'ko'),
            'meta' => [
                'locale' => $worker->locale ?: 'ko',
                'total' => $items->count(),
                'checked_count' => $items->whereIn('id', $checked)->count(),
                // 전부 확인했으면 앱이 완료 표시를 띄운다.
                'completed' => $items->isNotEmpty() && $items->whereNotIn('id', $checked)->isEmpty(),
            ],
        ]);
    }

    /** @return list<int> */
    private function checkedIds(Worker $worker): array
    {
        return LifeChecklistCheck::query()
            ->where('worker_id', $worker->id)
            ->pluck('life_checklist_item_id')
            ->all();
    }

    /**
     * 항목을 근로자 언어로 옮겨 담는다.
     *
     * 문구를 하나씩 번역하면 항목 수만큼 요청이 나간다. 전부 모아 한 번에 보낸다.
     *
     * @param  Collection<int, LifeChecklistItem>  $items
     * @param  list<int>  $checked
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $items, array $checked, string $locale): array
    {
        $texts = $items->flatMap(fn (LifeChecklistItem $i) => array_filter([$i->label, $i->hint]))
            ->unique()->values()->all();

        $map = [];
        if ($locale !== 'ko' && $texts !== []) {
            $map = array_combine($texts, GoogleTranslator::translateLines($texts, $locale, 'ko'));
        }

        return $items->map(fn (LifeChecklistItem $i) => [
            'id' => $i->id,
            'code' => $i->code,
            'label' => $map[$i->label] ?? $i->label,
            'hint' => $i->hint === null ? null : ($map[$i->hint] ?? $i->hint),
            'checked' => in_array($i->id, $checked, true),
        ])->all();
    }
}
