<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenSpout\Reader\CSV\Reader;

/** 후보자 wwGrid CRUD + 엑셀. */
class CandidateGridController extends Controller
{
    public static function mapRow(Candidate $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'nationality' => $c->nationality,
            'age' => $c->age,
            'gender' => $c->gender,
            'status' => $c->status->value,
            'queue_position' => $c->queue_position,
        ];
    }

    /** @return array<int, array<string,mixed>> */
    public static function rows(): array
    {
        return Candidate::latest('id')->limit(2000)->get()
            ->map(fn (Candidate $c) => self::mapRow($c))->all();
    }

    private function rowRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'nationality' => ['required', 'string', 'size:2'],
            'age' => ['nullable', 'integer', 'min:18', 'max:70'],
            'gender' => ['nullable', 'in:male,female'],
            'status' => ['nullable', 'in:applied,passed,held,rejected'],
            'queue_position' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function fillable(array $row): array
    {
        return [
            'name' => isset($row['name']) ? trim((string) $row['name']) : null,
            'nationality' => isset($row['nationality']) ? strtoupper(trim((string) $row['nationality'])) : null,
            // wwGrid 는 빈 숫자 셀을 0 으로 채우므로 0/빈값은 null 로 취급
            'age' => (($row['age'] ?? '') === '' || (int) $row['age'] === 0) ? null : (int) $row['age'],
            'gender' => ($row['gender'] ?? '') ?: null,
            'status' => ($row['status'] ?? '') ?: CandidateStatus::Applied->value,
            'queue_position' => (($row['queue_position'] ?? '') === '' || (int) $row['queue_position'] < 1) ? null : (int) $row['queue_position'],
        ];
    }

    public function save(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'updated' => ['array'], 'added' => ['array'], 'deleted' => ['array'],
        ]);

        try {
            DB::transaction(function () use ($payload) {
                $delIds = collect($payload['deleted'] ?? [])->pluck('id')->filter()->all();
                if ($delIds) {
                    Candidate::whereIn('id', $delIds)->delete();
                }
                foreach ($payload['updated'] ?? [] as $i => $u) {
                    $cur = $u['current'] ?? [];
                    if (empty($cur['id'])) {
                        continue;
                    }
                    $fields = $this->fillable($cur);
                    $this->validateRow($fields, "수정 {$i}행");
                    Candidate::whereKey($cur['id'])->update($fields);
                }
                foreach ($payload['added'] ?? [] as $i => $a) {
                    $fields = $this->fillable($a);
                    $this->validateRow($fields, "신규 {$i}행");
                    Candidate::create($fields);
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

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']]);

        $map = [
            '이름' => 'name', '성명' => 'name', '국적' => 'nationality',
            '나이' => 'age', '연령' => 'age', '성별' => 'gender', '상태' => 'status', '대기순번' => 'queue_position',
        ];
        $genderMap = ['남성' => 'male', '여성' => 'female', '남' => 'male', '여' => 'female', 'male' => 'male', 'female' => 'female'];
        $statusMap = ['지원' => 'applied', '합격' => 'passed', '보류' => 'held', '불합격' => 'rejected',
            'applied' => 'applied', 'passed' => 'passed', 'held' => 'held', 'rejected' => 'rejected'];
        $natMap = ['방글라데시' => 'BD', '방글라' => 'BD', '라오스' => 'LA', '스리랑카' => 'LK', '베트남' => 'VN'];

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
                        $header = array_map(fn ($h) => $map[trim($h)] ?? null, $cells);

                        continue;
                    }
                    $row = ['status' => 'applied'];
                    foreach ($header as $ci => $field) {
                        if ($field) {
                            $row[$field] = trim($cells[$ci] ?? '');
                        }
                    }
                    if (empty($row['name'])) {
                        continue;
                    }
                    if (isset($row['gender'])) {
                        $row['gender'] = $genderMap[$row['gender']] ?? null;
                    }
                    $row['status'] = $statusMap[$row['status'] ?? ''] ?? 'applied';
                    if (isset($row['age'])) {
                        $row['age'] = (int) $row['age'];
                    }
                    if (isset($row['nationality'])) {
                        $n = trim($row['nationality']);
                        $row['nationality'] = $natMap[$n] ?? strtoupper($n);
                    }
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
}
