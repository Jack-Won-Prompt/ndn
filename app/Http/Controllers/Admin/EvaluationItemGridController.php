<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Recruitment\Models\EvaluationItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * 면접 평가 체크리스트 항목 wwGrid CRUD.
 *
 * 항목·배점을 운영 중에 조정한다. 합격/보류 판정은 만점 대비 비율이므로
 * (EvaluateCandidateAction) 배점을 바꿔도 기준이 어긋나지 않는다.
 *
 * 지난 평가의 scores 는 당시 key 로 남아 있으므로, 항목을 지워도 이력은 보존된다.
 */
class EvaluationItemGridController extends Controller
{
    /** @return array<int, array<string, mixed>> */
    public static function rows(): array
    {
        return EvaluationItem::orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (EvaluationItem $i) => [
                'id' => $i->id,
                'key' => $i->key,
                'label' => $i->label,
                'hint' => $i->hint,
                'max_score' => $i->max_score,
                'sort_order' => $i->sort_order,
                'active' => $i->active ? 1 : 0,
            ])->all();
    }

    public function save(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'updated' => ['array'],
            'added' => ['array'],
            'deleted' => ['array'],
        ]);

        try {
            DB::transaction(function () use ($payload) {
                $del = collect($payload['deleted'] ?? [])->pluck('id')->filter()->all();
                if ($del) {
                    EvaluationItem::whereIn('id', $del)->delete();
                }

                foreach ($payload['updated'] ?? [] as $i => $u) {
                    $cur = $u['current'] ?? [];
                    if (empty($cur['id'])) {
                        continue;
                    }
                    $f = $this->fields($cur);
                    $this->check($f, $this->rules((int) $cur['id']), "평가 항목 수정 {$i}행");
                    EvaluationItem::whereKey($cur['id'])->update($f);
                }

                foreach ($payload['added'] ?? [] as $i => $a) {
                    $f = $this->fields($a);
                    $this->check($f, $this->rules(null), "평가 항목 신규 {$i}행");
                    EvaluationItem::create($f);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $max = EvaluationItem::totalMaxScore();

        return response()->json([
            'ok' => true,
            'message' => "저장했습니다. 현재 시트 만점 {$max}점 (합격 70% · 보류 50%).",
            'rows' => self::rows(),
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(?int $ignoreId): array
    {
        return [
            // key 는 평가 점수 배열의 키다. 영문 소문자·숫자·밑줄만 허용한다.
            'key' => [
                'required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('evaluation_items', 'key')->ignore($ignoreId),
            ],
            'label' => ['required', 'string', 'max:100'],
            'hint' => ['nullable', 'string', 'max:200'],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function fields(array $r): array
    {
        return [
            'key' => isset($r['key']) ? trim((string) $r['key']) : null,
            'label' => isset($r['label']) ? trim((string) $r['label']) : null,
            'hint' => filled($r['hint'] ?? null) ? trim((string) $r['hint']) : null,
            'max_score' => (int) ($r['max_score'] ?? 0),
            'sort_order' => (int) ($r['sort_order'] ?? 0),
            'active' => (bool) ($r['active'] ?? true),
        ];
    }

    private function check(array $fields, array $rules, string $label): void
    {
        $v = Validator::make($fields, $rules);
        if ($v->fails()) {
            throw new \RuntimeException($label.': '.$v->errors()->first());
        }
    }
}
