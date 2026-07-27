<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Monitoring\Actions\RecordMonthlyInterviewAction;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 월별 점검 — 본사(관리자) 직접 입력.
 *
 * 농가 방문 점검과 별개로, 관리자가 근로자 월별 점검(6항목)을 직접 기록한다(source=inspector).
 */
class MonitoringController extends Controller
{
    /** 점검 입력 폼용 활성 근로자 옵션. */
    public static function workerOptions(): array
    {
        return Worker::where('status', WorkerStatus::Active->value)
            ->orderBy('name')->limit(2000)->get(['id', 'name', 'nationality'])
            ->map(fn (Worker $w) => ['value' => $w->id, 'label' => $w->name.' ('.$w->nationality.')'])
            ->all();
    }

    /** 6항목 라벨 (true=양호). */
    public static function itemLabels(): array
    {
        $labels = [
            'pay_received' => '급여 수령', 'no_discrimination' => '차별 없음', 'follows_rules' => '생활 규칙',
            'adapts_group' => '단체생활', 'health_ok' => '건강', 'no_flight_signs' => '이탈징후 없음',
        ];

        return array_map(fn ($k, $v) => ['key' => $k, 'label' => $v], array_keys($labels), $labels);
    }

    /** 본사 직접 점검 입력 저장. */
    public function store(Request $request, RecordMonthlyInterviewAction $action): JsonResponse
    {
        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'interviewed_on' => ['required', 'date'],
            'items' => ['nullable', 'array'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ]);

        $worker = Worker::findOrFail($data['worker_id']);
        $items = [];
        foreach (MonthlyInterview::ITEMS as $item) {
            $items[$item] = filter_var($data['items'][$item] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $action->execute($worker, Auth::user(), $data['interviewed_on'], $items, $data['memo'] ?? null);

        return response()->json(['ok' => true]);
    }
}
