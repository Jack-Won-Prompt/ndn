<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenSpout\Reader\CSV\Reader;

/** 기준정보(농가·지자체) wwGrid CRUD + 엑셀. */
class BaseInfoGridController extends Controller
{
    /* ============================ 지자체(City) ============================ */

    public static function cityRows(): array
    {
        return City::orderBy('name')->get()
            ->map(fn (City $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'region' => $c->region,
                // 지역별 모집 조건 — 정원이 차면 그 지역 가입이 막힌다(City::isOpenForSignup)
                'quota' => $c->quota,
                'recruiting' => $c->recruiting ? 1 : 0,
            ])->all();
    }

    /**
     * 농가 표의 '지자체' 콤보에 넣을 선택지.
     *
     * 기준정보와 매칭 화면이 같은 농가 표를 쓰므로 선택지도 한 군데서 만든다 —
     * 두 벌로 갈라지면 한쪽에서만 보이는 지자체가 생긴다.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public static function cityOptions(): array
    {
        return City::orderBy('name')->get(['id', 'name'])
            ->map(fn (City $c) => ['value' => $c->id, 'label' => $c->name])
            ->all();
    }

    /** 지자체 행 검증 규칙 (신규·수정 공통) */
    private function cityRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'region' => ['nullable', 'string', 'max:50'],
            'quota' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'recruiting' => ['boolean'],
        ];
    }

    public function citySave(Request $request): JsonResponse
    {
        $payload = $request->validate(['updated' => ['array'], 'added' => ['array'], 'deleted' => ['array']]);
        try {
            DB::transaction(function () use ($payload) {
                $del = collect($payload['deleted'] ?? [])->pluck('id')->filter()->all();
                if ($del) {
                    City::whereIn('id', $del)->delete();
                }
                foreach ($payload['updated'] ?? [] as $i => $u) {
                    $cur = $u['current'] ?? [];
                    if (empty($cur['id'])) {
                        continue;
                    }
                    $f = $this->cityFields($cur);
                    $this->check($f, $this->cityRules(), "지자체 수정 {$i}행");
                    City::whereKey($cur['id'])->update($f);
                }
                foreach ($payload['added'] ?? [] as $i => $a) {
                    $f = $this->cityFields($a);
                    $this->check($f, $this->cityRules(), "지자체 신규 {$i}행");
                    City::create($f);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => '저장했습니다.', 'rows' => self::cityRows()]);
    }

    private function cityFields(array $r): array
    {
        return [
            'name' => isset($r['name']) ? trim((string) $r['name']) : null,
            'region' => isset($r['region']) ? trim((string) $r['region']) : null,
            // 빈 칸 = 정원 제한 없음
            'quota' => filled($r['quota'] ?? null) ? (int) $r['quota'] : null,
            'recruiting' => (bool) ($r['recruiting'] ?? true),
        ];
    }

    /* ============================== 농가(Farm) ============================== */

    public static function farmRows(): array
    {
        return Farm::orderBy('name')->get()
            ->map(fn (Farm $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'city_id' => $f->city_id,
                'main_crop' => $f->main_crop,
                'contact_phone' => $f->contact_phone,
                'address' => $f->address,
                // 농업경영체 등록번호 — 지자체 배정 신청서에 함께 적어 내는 번호
                'business_reg_no' => $f->business_reg_no,
            ])->all();
    }

    public function farmSave(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'updated' => ['array'],
            'added' => ['array'],
            'deleted' => ['array'],
            // 어느 화면이 부르는지 — 저장 뒤 돌려줄 목록의 모양이 달라진다.
            'rows' => ['nullable', 'in:matching'],
        ]);
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'main_crop' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:200'],
            // 숫자·하이픈만. 자리수를 고정하지 않는 이유는 발급 기관과 시기에 따라
            // 표기가 달라서다 — 틀린 규칙으로 막으면 맞는 번호를 못 넣는다.
            'business_reg_no' => ['nullable', 'string', 'max:30', 'regex:/^[0-9-]+$/'],
        ];
        $messages = ['business_reg_no.regex' => '경영체등록번호는 숫자와 - 만 넣을 수 있습니다.'];
        try {
            DB::transaction(function () use ($payload, $rules, $messages) {
                $del = collect($payload['deleted'] ?? [])->pluck('id')->filter()->all();
                if ($del) {
                    Farm::whereIn('id', $del)->get()->each->delete();
                }
                foreach ($payload['updated'] ?? [] as $i => $u) {
                    $cur = $u['current'] ?? [];
                    if (empty($cur['id'])) {
                        continue;
                    }
                    $f = $this->farmFields($cur);
                    $this->check($f, $rules, "농가 수정 {$i}행", $messages);
                    Farm::whereKey($cur['id'])->update($f);
                }
                foreach ($payload['added'] ?? [] as $i => $a) {
                    $f = $this->farmFields($a);
                    $this->check($f, $rules, "농가 신규 {$i}행", $messages);
                    Farm::create($f);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => '저장했습니다.',
            'rows' => self::rowsFor($payload['rows'] ?? null),
        ]);
    }

    /**
     * 저장 뒤 화면을 다시 그릴 때 쓰는 목록.
     *
     * 농가 표는 기준정보와 매칭 화면이 함께 쓰는데, 매칭 화면에는 수요·배정 숫자와
     * [인력 배정] 칸이 더 있다. 늘 기준정보 모양으로만 돌려주면 매칭 화면에서
     * 저장한 순간 그 칸들이 빈칸이 되고, 방금 등록한 농가에 사람을 붙일 수 없게 된다.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function rowsFor(?string $view): array
    {
        return $view === 'matching'
            ? MatchingController::farmRows()
            : self::farmRows();
    }

    private function farmFields(array $r): array
    {
        return [
            'name' => isset($r['name']) ? trim((string) $r['name']) : null,
            'city_id' => ($r['city_id'] ?? '') === '' ? null : (int) $r['city_id'],
            'main_crop' => ($r['main_crop'] ?? '') ?: null,
            'contact_phone' => ($r['contact_phone'] ?? '') ?: null,
            'address' => ($r['address'] ?? '') ?: null,
            // 보기 좋으라고 넣은 공백은 지운다. 같은 번호가 '1234567890' 과
            // '123 456 7890' 으로 갈라지면 나중에 대조할 수 없다.
            'business_reg_no' => trim(str_replace(' ', '', (string) ($r['business_reg_no'] ?? ''))) ?: null,
        ];
    }

    public function farmImport(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']]);
        // 받는 엑셀마다 머리글이 달라 흔한 표기를 모두 받는다.
        $map = ['농가' => 'name', '농가명' => 'name', '지자체' => 'city', '시청' => 'city',
            '품목' => 'main_crop', '주작물' => 'main_crop', '연락처' => 'contact_phone', '전화' => 'contact_phone', '주소' => 'address',
            '경영체등록번호' => 'business_reg_no', '농업경영체등록번호' => 'business_reg_no', '경영체번호' => 'business_reg_no'];
        $cities = City::pluck('id', 'name');
        try {
            $ext = strtolower($request->file('file')->getClientOriginalExtension());
            $reader = in_array($ext, ['csv', 'txt'], true) ? new Reader : new \OpenSpout\Reader\XLSX\Reader;
            $reader->open($request->file('file')->getPathname());
            $rows = [];
            $header = null;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $r) {
                    $cells = array_map(fn ($c) => (string) $c->getValue(), $r->getCells());
                    if ($header === null) {
                        // 머리글의 공백은 무시한다 — '경영체등록번호' 와 '경영체 등록번호' 를
                        // 다른 칸으로 보면 열 하나가 통째로 버려진다.
                        $header = array_map(
                            fn ($h) => $map[preg_replace('/\s+/u', '', trim($h))] ?? null,
                            $cells,
                        );

                        continue;
                    }
                    $row = [];
                    foreach ($header as $ci => $field) {
                        if ($field) {
                            $row[$field] = trim($cells[$ci] ?? '');
                        }
                    }
                    if (empty($row['name'])) {
                        continue;
                    }
                    $row['city_id'] = $cities[$row['city'] ?? ''] ?? null;
                    unset($row['city']);
                    $rows[] = $row;
                }
                break;
            }
            $reader->close();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => '엑셀을 읽지 못했습니다: '.$e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    /** @param  array<string, string>  $messages */
    private function check(array $fields, array $rules, string $label, array $messages = []): void
    {
        $v = Validator::make($fields, $rules, $messages);
        if ($v->fails()) {
            throw new \RuntimeException($label.': '.$v->errors()->first());
        }
    }
}
