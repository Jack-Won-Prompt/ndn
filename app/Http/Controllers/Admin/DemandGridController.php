<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenSpout\Reader\CSV\Reader;

/**
 * 수요 신청 wwGrid CRUD (등록/수정/삭제) + 엑셀 가져오기.
 * 관리자가 시·농가를 대신해 수요 신청을 등록·관리한다.
 */
class DemandGridController extends Controller
{
    /** 그리드 1행 형태로 매핑 (블레이드·저장 응답 공용) */
    public static function mapRow(DemandRequest $d): array
    {
        return [
            'id' => $d->id,
            'farm_id' => $d->farm_id,
            'city_id' => $d->city_id,
            'nationality' => $d->nationality,
            'headcount' => $d->headcount,
            'gender' => $d->gender->value,
            'crop' => $d->crop,
            'period_start' => $d->period_start?->format('Y-m-d'),
            'period_end' => $d->period_end?->format('Y-m-d'),
            'status' => $d->status->value,
            'note' => $d->note,
        ];
    }

    /** @return array<int, array<string,mixed>> */
    public static function rows(): array
    {
        return DemandRequest::latest('id')->limit(2000)->get()
            ->map(fn (DemandRequest $d) => self::mapRow($d))->all();
    }

    private function rowRules(): array
    {
        return [
            'farm_id' => ['required', 'integer', 'exists:farms,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'nationality' => ['required', 'string', 'size:2'],
            'headcount' => ['required', 'integer', 'min:1', 'max:999'],
            'gender' => ['required', 'in:male,female,any'],
            'crop' => ['required', 'string', 'max:100'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'status' => ['nullable', 'in:draft,submitted,aggregated,letter_issued,rejected'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** 한 행에서 저장 대상 필드만 추출 */
    private function fillable(array $row): array
    {
        return [
            'farm_id' => $row['farm_id'] ?? null,
            'city_id' => $row['city_id'] ?? null,
            'nationality' => isset($row['nationality']) ? strtoupper(trim((string) $row['nationality'])) : null,
            'headcount' => $row['headcount'] ?? null,
            'gender' => $row['gender'] ?? null,
            'crop' => $row['crop'] ?? null,
            'period_start' => $row['period_start'] ?? null,
            'period_end' => $row['period_end'] ?? null,
            'status' => $row['status'] ?? DemandStatus::Draft->value,
            'note' => $row['note'] ?? null,
        ];
    }

    /** 변경 저장: {updated, added, deleted} */
    public function save(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'updated' => ['array'],
            'added' => ['array'],
            'deleted' => ['array'],
        ]);

        try {
            DB::transaction(function () use ($payload) {
                // 삭제
                $delIds = collect($payload['deleted'] ?? [])->pluck('id')->filter()->all();
                if ($delIds) {
                    DemandRequest::whereIn('id', $delIds)->get()->each->delete();
                }

                // 수정
                foreach ($payload['updated'] ?? [] as $i => $u) {
                    $cur = $u['current'] ?? [];
                    $id = $cur['id'] ?? null;
                    if (! $id) {
                        continue;
                    }
                    $fields = $this->fillable($cur);
                    $this->validateRow($fields, "수정 {$i}행");
                    DemandRequest::whereKey($id)->update($fields);
                }

                // 추가
                foreach ($payload['added'] ?? [] as $i => $a) {
                    $fields = $this->fillable($a);
                    $this->validateRow($fields, "신규 {$i}행");
                    DemandRequest::create($fields);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => '저장했습니다.',
            'rows' => self::rows(),
        ]);
    }

    private function validateRow(array $fields, string $label): void
    {
        $v = Validator::make($fields, $this->rowRules());
        if ($v->fails()) {
            throw new \RuntimeException($label.': '.$v->errors()->first());
        }
    }

    /** 엑셀/CSV 업로드 → 파싱 후 그리드용 rows 반환 (저장 아님, 검토용) */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        // 헤더(한글) → 필드 매핑
        $map = [
            '농가' => 'farm', '농가명' => 'farm', '시청' => 'city', '지자체' => 'city',
            '국적' => 'nationality', '인원' => 'headcount', '성별' => 'gender',
            '품목' => 'crop', '시작일' => 'period_start', '종료일' => 'period_end',
            '상태' => 'status', '비고' => 'note',
        ];
        $genderMap = ['남성' => 'male', '여성' => 'female', '무관' => 'any', 'male' => 'male', 'female' => 'female', 'any' => 'any'];
        $natMap = ['방글라데시' => 'BD', '방글라' => 'BD', '라오스' => 'LA', '스리랑카' => 'LK', '베트남' => 'VN'];
        $statusMap = ['작성 중' => 'draft', '작성중' => 'draft', '제출' => 'submitted', '취합' => 'aggregated',
            '레터 발행' => 'letter_issued', '레터발행' => 'letter_issued', '반려' => 'rejected',
            'draft' => 'draft', 'submitted' => 'submitted', 'aggregated' => 'aggregated', 'letter_issued' => 'letter_issued', 'rejected' => 'rejected'];

        $farms = Farm::pluck('id', 'name');   // name => id
        $cities = City::pluck('id', 'name');

        try {
            $ext = strtolower($request->file('file')->getClientOriginalExtension());
            $reader = in_array($ext, ['csv', 'txt'], true)
                ? new Reader
                : new \OpenSpout\Reader\XLSX\Reader;
            $reader->open($request->file('file')->getPathname());

            $rows = [];
            $header = null;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $r) {
                    $cells = array_map(fn ($c) => (string) $c->getValue(), $r->getCells());
                    if ($header === null) {
                        $header = array_map(fn ($h) => $map[trim($h)] ?? null, $cells);

                        continue;
                    }
                    $row = ['status' => 'draft'];
                    foreach ($header as $ci => $field) {
                        if (! $field) {
                            continue;
                        }
                        $row[$field] = trim($cells[$ci] ?? '');
                    }
                    // 농가/시청 이름 → id
                    $row['farm_id'] = $farms[$row['farm'] ?? ''] ?? null;
                    $row['city_id'] = $cities[$row['city'] ?? ''] ?? null;
                    unset($row['farm'], $row['city']);
                    if (isset($row['gender'])) {
                        $row['gender'] = $genderMap[$row['gender']] ?? 'any';
                    }
                    $row['status'] = $statusMap[$row['status'] ?? ''] ?? 'draft';
                    if (isset($row['headcount'])) {
                        $row['headcount'] = (int) $row['headcount'];
                    }
                    if (isset($row['nationality'])) {
                        $n = trim($row['nationality']);
                        $row['nationality'] = $natMap[$n] ?? strtoupper($n);
                    }
                    $rows[] = $row;
                }
                break; // 첫 시트만
            }
            $reader->close();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => '엑셀을 읽지 못했습니다: '.$e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'rows' => $rows]);
    }
}
