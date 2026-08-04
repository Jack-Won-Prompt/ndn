<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Models\City;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenSpout\Reader\CSV\Reader;

/**
 * 근로자 wwGrid CRUD + 엑셀.
 *
 * CLAUDE.md §7(절대 규칙) 준수:
 *  - 그리드/엑셀 다운로드에는 민감필드(passport_no·birth_date·phone_home_country)를
 *    노출하지 않는다. (40행 일괄 표시는 감사로그 없는 대량 열람이 되므로 금지)
 *  - 엑셀 업로드로 민감필드를 받을 수는 있으나, 파싱 후 서버에서 곧바로 암호화 저장하고
 *    브라우저 그리드로는 비민감 컬럼만 되돌린다. (민감값이 클라이언트로 왕복하지 않음)
 */
class WorkerGridController extends Controller
{
    private const LOCALES = ['ko', 'bn', 'lo', 'si', 'vi', 'ne', 'ky'];

    /** 비민감 필드만 매핑 (그리드·엑셀 다운로드 공용) */
    public static function mapRow(Worker $w): array
    {
        return [
            'id' => $w->id,
            'name' => $w->name,
            'nationality' => $w->nationality,
            // 지원 지자체 — 가입 시 근로자가 고르며, 이전 가입자는 여기서 채운다
            'city_id' => $w->city_id,
            'locale' => $w->locale,
            'status' => $w->status->value,
        ];
    }

    /** @return array<int, array<string,mixed>> */
    public static function rows(): array
    {
        return Worker::latest('id')->limit(2000)->get()
            ->map(fn (Worker $w) => self::mapRow($w))->all();
    }

    private function rowRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'nationality' => ['required', 'string', 'size:2'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'locale' => ['required', 'in:'.implode(',', self::LOCALES)],
            'status' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** 비민감 필드만 추출 (그리드 편집은 민감필드를 다루지 않는다) */
    private function nonSensitive(array $row): array
    {
        return [
            'name' => isset($row['name']) ? trim((string) $row['name']) : null,
            'nationality' => isset($row['nationality']) ? strtoupper(trim((string) $row['nationality'])) : null,
            // 빈 문자열(미선택)은 null 로 — exists 검증에 걸리지 않게 한다
            'city_id' => filled($row['city_id'] ?? null) ? (int) $row['city_id'] : null,
            'locale' => $row['locale'] ?? 'ko',
            'status' => $row['status'] ?? 'active',
        ];
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
                $delIds = collect($payload['deleted'] ?? [])->pluck('id')->filter()->all();
                if ($delIds) {
                    Worker::whereIn('id', $delIds)->get()->each->delete();
                }

                foreach ($payload['updated'] ?? [] as $i => $u) {
                    $cur = $u['current'] ?? [];
                    if (empty($cur['id'])) {
                        continue;
                    }
                    $fields = $this->nonSensitive($cur);
                    $this->validateRow($fields, "수정 {$i}행");
                    // 민감필드는 건드리지 않고 비민감만 업데이트
                    Worker::whereKey($cur['id'])->update($fields);
                }

                foreach ($payload['added'] ?? [] as $i => $a) {
                    $fields = $this->nonSensitive($a);
                    $this->validateRow($fields, "신규 {$i}행");
                    Worker::create($fields);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => '저장했습니다.', 'rows' => self::rows()]);
    }

    private function validateRow(array $fields, string $label): void
    {
        $v = Validator::make($fields, $this->rowRules());
        if ($v->fails()) {
            throw new \RuntimeException($label.': '.$v->errors()->first());
        }
    }

    /**
     * 엑셀/CSV 업로드 → 서버에서 곧바로 근로자 생성(민감필드 암호화 저장).
     * 민감값을 브라우저로 되돌리지 않고, 갱신된 비민감 rows 만 반환한다(§7).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $map = [
            '이름' => 'name', '성명' => 'name',
            '국적' => 'nationality', '언어' => 'locale', '상태' => 'status',
            '지역' => 'city', '지자체' => 'city', '시군' => 'city',
            '여권번호' => 'passport_no', '생년월일' => 'birth_date', '본국전화' => 'phone_home_country', '전화' => 'phone_home_country',
        ];
        $natMap = ['방글라데시' => 'BD', '방글라' => 'BD', '라오스' => 'LA', '스리랑카' => 'LK', '베트남' => 'VN'];
        // 지역명(예: 당진시) → city_id. 없는 이름은 무시하고 미지정으로 둔다.
        $cityMap = City::pluck('id', 'name');

        try {
            $ext = strtolower($request->file('file')->getClientOriginalExtension());
            $reader = in_array($ext, ['csv', 'txt'], true) ? new Reader : new \OpenSpout\Reader\XLSX\Reader;
            $reader->open($request->file('file')->getPathname());

            $created = 0;
            DB::transaction(function () use ($reader, $map, $natMap, $cityMap, &$created) {
                $header = null;
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $r) {
                        $cells = array_map(fn ($c) => (string) $c->getValue(), $r->getCells());
                        if ($header === null) {
                            $header = array_map(fn ($h) => $map[trim($h)] ?? null, $cells);

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
                        $nat = trim($row['nationality'] ?? '');
                        Worker::create([
                            'name' => $row['name'],
                            'nationality' => $natMap[$nat] ?? strtoupper($nat),
                            'city_id' => $cityMap[trim($row['city'] ?? '')] ?? null,
                            'locale' => in_array($row['locale'] ?? '', self::LOCALES, true) ? $row['locale'] : 'ko',
                            'status' => ($row['status'] ?? '') ?: 'active',
                            // 민감필드: 있으면 암호화 cast 로 저장됨
                            'passport_no' => $row['passport_no'] ?? null,
                            'birth_date' => $row['birth_date'] ?? null,
                            'phone_home_country' => $row['phone_home_country'] ?? null,
                        ]);
                        $created++;
                    }
                    break;
                }
            });
            $reader->close();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => '엑셀을 읽지 못했습니다: '.$e->getMessage()], 422);
        }

        // 민감값은 반환하지 않음. 갱신된 비민감 전체 rows 로 그리드 교체.
        return response()->json(['ok' => true, 'message' => "{$created}명 등록됨", 'rows' => self::rows(), 'replace' => true]);
    }
}
