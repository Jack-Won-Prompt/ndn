<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Models\City;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenSpout\Reader\CSV\Reader;
use RuntimeException;
use Throwable;

/**
 * 근로자 wwGrid CRUD + 엑셀.
 *
 * 목록에 여권번호·생년월일·연락처·이메일·비고가 함께 나온다. 본사 담당자가
 * 관공서 제출 서류를 만들고 현지와 대조하는 자리라, 한 사람씩 열어 보게 하면
 * 40명을 40번 열어야 한다.
 *
 * 그래서 지키는 선은 다음과 같다 (CLAUDE.md §7).
 *
 *  - 저장은 여전히 암호화 cast 를 지난다(§7-1). 화면에 보인다고 DB 가 평문이 되지 않는다.
 *  - 로그·예외 메시지에는 나오지 않는다 — 모델의 MasksSensitiveData 가 toArray 를 가린다.
 *    이 컨트롤러는 속성을 직접 읽어 화면에만 내보낸다.
 *  - **목록을 여는 것 자체가 개인정보 열람**이므로 누가 몇 명분을 봤는지 남긴다(§7-6).
 *    한 사람씩이 아니라 한 줄로 묶어 남긴다 — 건별로 남기면 로그가 목록의 복사본이 된다.
 *  - 이 화면은 ndn_admin 전용이다(라우트 미들웨어). 대리점·시청 포털에는 열리지 않는다.
 */
class WorkerGridController extends Controller
{
    private const LOCALES = ['ko', 'bn', 'lo', 'si', 'vi', 'ne', 'ky'];

    /** 한 번에 내려보내는 최대 인원. 복호화가 행마다 일어나므로 무한정 늘리지 않는다. */
    private const MAX_ROWS = 2000;

    /** 그리드·엑셀 다운로드 공용 한 줄 */
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
            // 아래 넷은 암호화되어 저장된 값을 복호화해 보여 준다(§7-1 그대로 유지).
            // plain() 으로 읽는 이유는, 다른 APP_KEY 로 암호화된 행이 하나 섞여 있어도
            // 목록 전체가 500 으로 죽지 않게 하기 위해서다.
            'phone_home_country' => $w->plain('phone_home_country'),
            'email' => $w->email,
            'passport_no' => $w->plain('passport_no'),
            'birth_date' => $w->plain('birth_date'),
            'note' => $w->note,
        ];
    }

    /** @return array<int, array<string,mixed>> */
    public static function rows(): array
    {
        $workers = Worker::latest('id')->limit(self::MAX_ROWS)->get();

        self::logAccess($workers->pluck('id')->all());

        return $workers->map(fn (Worker $w) => self::mapRow($w))->all();
    }

    /**
     * 개인정보를 화면에 띄웠다는 기록 (§7-6).
     *
     * 목록 한 번에 한 줄로 남긴다. 사람마다 남기면 로그가 목록을 그대로 베낀 것이
     * 되어, 정작 "누가 언제 이 명단을 봤나" 를 찾을 때 파묻힌다.
     *
     * @param  list<int>  $workerIds
     */
    private static function logAccess(array $workerIds): void
    {
        if ($workerIds === [] || Auth::user() === null) {
            return;
        }

        activity('personal-data-access')
            ->causedBy(Auth::user())
            ->withProperties([
                'reason' => 'worker-grid',
                'worker_ids' => $workerIds,
                'count' => count($workerIds),
                // 목록에 여권번호·생년월일까지 나왔다는 사실을 기록에 박아 둔다.
                'fields' => ['passport_no', 'birth_date', 'phone_home_country', 'email'],
            ])
            ->log('개인정보 열람: 근로자 목록');
    }

    private function rowRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'nationality' => ['required', 'string', 'size:2'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'locale' => ['required', 'in:'.implode(',', self::LOCALES)],
            'status' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_home_country' => ['nullable', 'string', 'max:40'],
            'passport_no' => ['nullable', 'string', 'max:64'],
            'birth_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * 한 행에서 저장할 값만 추린다.
     *
     * 빈 칸은 null 로 넣는다 — 그래야 담당자가 칸을 비워 지울 수 있다. 다만
     * 그리드에서 온 행에는 모든 칸이 실려 오므로, 화면에서 안 건드린 값이
     * 실수로 지워지는 일은 없다.
     */
    private function fields(array $row): array
    {
        $text = fn (string $k) => filled($row[$k] ?? null) ? trim((string) $row[$k]) : null;

        return [
            'name' => isset($row['name']) ? trim((string) $row['name']) : null,
            'nationality' => isset($row['nationality']) ? strtoupper(trim((string) $row['nationality'])) : null,
            // 빈 문자열(미선택)은 null 로 — exists 검증에 걸리지 않게 한다
            'city_id' => filled($row['city_id'] ?? null) ? (int) $row['city_id'] : null,
            'locale' => $row['locale'] ?? 'ko',
            'status' => $row['status'] ?? 'active',
            'email' => $text('email'),
            'phone_home_country' => $text('phone_home_country'),
            // 공백을 지운다. 같은 여권번호가 띄어쓰기 때문에 다른 값으로 갈라지면
            // blind index 도 갈라져 같은 사람을 두 번 등록하게 된다.
            'passport_no' => filled($row['passport_no'] ?? null)
                ? str_replace(' ', '', trim((string) $row['passport_no']))
                : null,
            'birth_date' => $this->date($row['birth_date'] ?? null),
            'note' => $text('note'),
        ];
    }

    /**
     * 생년월일 정규화.
     *
     * 엑셀은 날짜를 1990-01-01 · 1990/1/1 · 19900101 처럼 제각각으로 준다.
     * 저장은 암호화 문자열이라 DB 가 형식을 바로잡아 주지 않으므로 여기서 맞춘다.
     */
    private function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $raw = trim((string) $value);

        if (preg_match('/^\d{8}$/', $raw)) {
            $raw = substr($raw, 0, 4).'-'.substr($raw, 4, 2).'-'.substr($raw, 6, 2);
        }

        $ts = strtotime(str_replace(['/', '.'], '-', $raw));

        return $ts === false ? $raw : date('Y-m-d', $ts);
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
                    $fields = $this->fields($cur);
                    $this->validateRow($fields, "수정 {$i}행", (int) $cur['id']);
                    // update() 가 아니라 모델을 채워 저장한다 — 그래야 암호화 cast 와
                    // blind index 갱신(saving 훅)이 함께 돈다.
                    $worker = Worker::findOrFail($cur['id']);
                    $worker->fill($fields)->save();
                }

                foreach ($payload['added'] ?? [] as $i => $a) {
                    $fields = $this->fields($a);
                    $this->validateRow($fields, "신규 {$i}행");
                    Worker::create($fields);
                }
            });
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => '저장했습니다.', 'rows' => self::rows()]);
    }

    /**
     * 한 행 검증 + 여권번호 중복 확인.
     *
     * 여권번호는 암호문이라 DB 유니크 제약을 걸 수 없다. 대신 blind index 로
     * 찾는다 — 같은 사람이 두 줄로 들어오면 배정·서류가 갈라진다.
     */
    private function validateRow(array $fields, string $label, ?int $ignoreId = null): void
    {
        // 기본 문구는 'validation.required' 로 나온다 — 어느 칸이 왜 걸렸는지
        // 알 수 없어 엑셀 몇백 줄에서 원인을 못 찾는다. 칸 이름을 붙여 준다.
        $v = Validator::make($fields, $this->rowRules(), [
            'required' => ':attribute 칸이 비어 있습니다.',
            'nationality.size' => '국적은 두 글자 코드로 적으세요 (BD·LA·LK·VN·NP·KG).',
            'email.email' => '이메일 형식이 아닙니다.',
            'birth_date.date' => '생년월일을 날짜로 읽지 못했습니다.',
            'city_id.exists' => '등록되지 않은 지역입니다.',
            'locale.in' => '쓸 수 없는 언어 코드입니다.',
        ], [
            'name' => '이름',
            'nationality' => '국적',
            'locale' => '언어',
            'email' => '이메일',
            'phone_home_country' => '연락처',
            'passport_no' => '여권번호',
            'birth_date' => '생년월일',
            'note' => '비고',
        ]);

        if ($v->fails()) {
            throw new RuntimeException($label.': '.$v->errors()->first());
        }

        if (blank($fields['passport_no'])) {
            return;
        }

        $dup = Worker::wherePassport($fields['passport_no'])
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->first();

        if ($dup !== null) {
            throw new RuntimeException("{$label}: 같은 여권번호가 이미 등록돼 있습니다 ({$dup->name}).");
        }
    }

    /**
     * 엑셀/CSV 업로드 — 있으면 고치고, 없으면 새로 넣는다.
     *
     * 무엇을 '같은 사람' 으로 볼지가 이 기능의 전부다. 순서대로 본다.
     *   1) 번호(id) 칸 — 엑셀 다운로드로 받은 그대로 고쳐 올릴 때
     *   2) 여권번호 — 현지에서 받은 명단에는 번호가 없다
     *   3) 둘 다 없으면 새 사람
     *
     * 이름으로는 맞추지 않는다. 동명이인이 흔해서 엉뚱한 사람을 덮어쓴다.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        // 받는 엑셀마다 머리글이 달라 흔한 표기를 모두 받는다(공백은 무시).
        $map = [
            '번호' => 'id', 'id' => 'id', 'ID' => 'id',
            '이름' => 'name', '성명' => 'name',
            '국적' => 'nationality', '언어' => 'locale', '상태' => 'status',
            '지역' => 'city', '지자체' => 'city', '시군' => 'city', '지원지역' => 'city',
            '여권번호' => 'passport_no', '여권' => 'passport_no',
            '생년월일' => 'birth_date', '생일' => 'birth_date',
            '연락처' => 'phone_home_country', '본국전화' => 'phone_home_country',
            '전화' => 'phone_home_country', '전화번호' => 'phone_home_country',
            '이메일' => 'email', '메일' => 'email',
            '비고' => 'note', '메모' => 'note',
        ];
        $natMap = ['방글라데시' => 'BD', '방글라' => 'BD', '라오스' => 'LA', '스리랑카' => 'LK', '베트남' => 'VN'];
        // 지역명(예: 당진시) → city_id. 없는 이름은 무시하고 미지정으로 둔다.
        $cityMap = City::pluck('id', 'name');

        $created = 0;
        $updated = 0;

        try {
            $ext = strtolower($request->file('file')->getClientOriginalExtension());
            $reader = in_array($ext, ['csv', 'txt'], true) ? new Reader : new \OpenSpout\Reader\XLSX\Reader;
            $reader->open($request->file('file')->getPathname());

            DB::transaction(function () use ($reader, $map, $natMap, $cityMap, &$created, &$updated) {
                $header = null;
                $line = 1;

                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $r) {
                        $cells = array_map(fn ($c) => (string) $c->getValue(), $r->getCells());

                        if ($header === null) {
                            $header = array_map(
                                fn ($h) => $map[preg_replace('/\s+/u', '', trim($h))] ?? null,
                                $cells,
                            );

                            continue;
                        }

                        $line++;
                        $row = [];
                        foreach ($header as $ci => $field) {
                            if ($field) {
                                $row[$field] = trim($cells[$ci] ?? '');
                            }
                        }

                        if (blank($row['name'] ?? null) && blank($row['passport_no'] ?? null)) {
                            continue;   // 빈 줄
                        }

                        $nat = trim($row['nationality'] ?? '');
                        $row['nationality'] = $natMap[$nat] ?? strtoupper($nat);
                        $row['city_id'] = $cityMap[trim($row['city'] ?? '')] ?? null;
                        $row['locale'] = in_array($row['locale'] ?? '', self::LOCALES, true) ? $row['locale'] : 'ko';
                        $row['status'] = ($row['status'] ?? '') ?: 'active';

                        $target = $this->findExisting($row);
                        $fields = $this->fields($row);

                        // 고칠 때는 엑셀에 없던 칸을 지우지 않는다 — 이름과 여권번호만
                        // 적어 온 명단으로 나머지가 통째로 비워지면 안 된다.
                        if ($target !== null) {
                            $target->fill(array_filter($fields, fn ($v) => filled($v)))->save();
                            $updated++;

                            continue;
                        }

                        $this->validateRow($fields, "{$line}행");
                        Worker::create($fields);
                        $created++;
                    }
                    break;
                }
            });
            $reader->close();
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => '엑셀을 읽지 못했습니다: '.$e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => "새로 등록 {$created}명 · 수정 {$updated}명",
            'rows' => self::rows(),
            'replace' => true,
        ]);
    }

    /** 이 줄이 가리키는 기존 근로자 (번호 → 여권번호 순). */
    private function findExisting(array $row): ?Worker
    {
        if (filled($row['id'] ?? null) && ctype_digit((string) $row['id'])) {
            return Worker::find((int) $row['id']);
        }

        $passport = filled($row['passport_no'] ?? null)
            ? str_replace(' ', '', trim((string) $row['passport_no']))
            : null;

        return $passport === null ? null : Worker::wherePassport($passport)->first();
    }
}
