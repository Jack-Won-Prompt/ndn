<?php

declare(strict_types=1);

use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Admin\MatchingController;
use App\Http\Controllers\Admin\WorkerGridController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * 근로자 목록 — 연락처·이메일·여권번호·생년월일·비고.
 *
 * 본사가 관공서 서류를 만들고 현지 명단과 대조하는 자리라 한 화면에 다 보여야
 * 한다. 대신 지켜야 할 선이 있다: 저장은 암호화를 지나고(§7-1), 목록을 연 것
 * 자체가 열람 기록으로 남는다(§7-6).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
});

function saveWorkers(array $added = [], array $updated = []): TestResponse
{
    return actingAs(test()->admin)->postJson(route('admin.grid.workers.save'), [
        'added' => $added,
        'updated' => $updated,
        'deleted' => [],
    ]);
}

it('목록에 다섯 항목이 함께 나온다', function () {
    $worker = Worker::factory()->create([
        'passport_no' => 'M12345678',
        'birth_date' => '1990-03-15',
        'phone_home_country' => '+880 1711-000000',
        'email' => 'w@example.com',
        'note' => '형과 함께 입국 예정',
    ]);

    actingAs($this->admin);
    $row = collect(WorkerGridController::rows())->firstWhere('id', $worker->id);

    expect($row['passport_no'])->toBe('M12345678')
        ->and($row['birth_date'])->toBe('1990-03-15')
        ->and($row['phone_home_country'])->toBe('+880 1711-000000')
        ->and($row['email'])->toBe('w@example.com')
        ->and($row['note'])->toBe('형과 함께 입국 예정');
});

it('화면에 보여도 DB 에는 암호문으로 남는다', function () {
    // 화면에 내보내는 것과 저장 방식은 별개다 (§7-1).
    saveWorkers([[
        'name' => 'Md. Rahman',
        'nationality' => 'BD',
        'locale' => 'bn',
        'passport_no' => 'BX9999999',
        'birth_date' => '1988-01-02',
        'phone_home_country' => '+880 1700-111111',
    ]])->assertOk();

    $raw = DB::table('workers')->where('name', 'Md. Rahman')->first();

    expect($raw->passport_no)->not->toBe('BX9999999')
        ->and($raw->birth_date)->not->toBe('1988-01-02')
        ->and($raw->phone_home_country)->not->toBe('+880 1700-111111')
        // 그래도 읽을 때는 평문으로 돌아온다.
        ->and(Worker::where('name', 'Md. Rahman')->firstOrFail()->passport_no)->toBe('BX9999999');
});

it('목록을 열면 열람 기록이 남는다', function () {
    Worker::factory()->count(3)->create();

    actingAs($this->admin)->get(url('admin/screen/workers'))->assertOk();

    $log = Activity::where('log_name', 'personal-data-access')
        ->where('properties->reason', 'worker-grid')
        ->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['count'])->toBe(3)
        // 무엇까지 보였는지가 기록에 남아야 한다.
        ->and($log->properties['fields'])->toContain('passport_no');
});

it('여권번호로 blind index 가 함께 갱신된다', function () {
    // update() 로 칸만 바꾸면 saving 훅이 돌지 않아 검색용 해시가 옛 값에 머문다.
    $worker = Worker::factory()->create(['passport_no' => 'OLD11111']);

    saveWorkers([], [[
        'current' => [
            'id' => $worker->id,
            'name' => $worker->name,
            'nationality' => $worker->nationality,
            'locale' => $worker->locale,
            'passport_no' => 'NEW22222',
        ],
    ]])->assertOk();

    expect(Worker::wherePassport('NEW22222')->pluck('id')->all())->toBe([$worker->id])
        ->and(Worker::wherePassport('OLD11111')->count())->toBe(0);
});

it('같은 여권번호를 두 번 넣지 못한다', function () {
    // 같은 사람이 두 줄로 들어오면 배정도 서류도 갈라진다.
    Worker::factory()->create(['name' => '먼저 등록', 'passport_no' => 'DUP12345']);

    $res = saveWorkers([[
        'name' => '나중 등록', 'nationality' => 'VN', 'locale' => 'vi', 'passport_no' => 'DUP12345',
    ]])->assertStatus(422);

    expect($res->json('message'))->toContain('먼저 등록')
        ->and(Worker::where('name', '나중 등록')->count())->toBe(0);
});

it('자기 여권번호는 그대로 두고 다른 칸만 고칠 수 있다', function () {
    $worker = Worker::factory()->create(['passport_no' => 'SAME1234']);

    saveWorkers([], [[
        'current' => [
            'id' => $worker->id,
            'name' => '이름만 변경',
            'nationality' => $worker->nationality,
            'locale' => $worker->locale,
            'passport_no' => 'SAME1234',
            'note' => '메모 추가',
        ],
    ]])->assertOk();

    expect($worker->fresh()->name)->toBe('이름만 변경')
        ->and($worker->fresh()->note)->toBe('메모 추가');
});

it('비고를 비우면 지워진다', function () {
    $worker = Worker::factory()->create(['note' => '지울 메모']);

    saveWorkers([], [[
        'current' => [
            'id' => $worker->id,
            'name' => $worker->name,
            'nationality' => $worker->nationality,
            'locale' => $worker->locale,
            'note' => '',
        ],
    ]])->assertOk();

    expect($worker->fresh()->note)->toBeNull();
});

/** 엑셀 업로드 흉내 */
function importWorkers(string $csv): TestResponse
{
    return actingAs(test()->admin)->post(route('admin.grid.workers.import'), [
        'file' => UploadedFile::fake()->createWithContent('workers.csv', $csv),
    ]);
}

it('엑셀 업로드로 다섯 항목까지 들어온다', function () {
    City::factory()->create(['name' => '당진시']);

    importWorkers(
        "이름,국적,지역,여권번호,생년월일,연락처,이메일,비고\n"
        ."Tran Van A,베트남,당진시,VN1234567,1992-07-01,+84 90-000-0000,a@example.com,딸기 경험\n"
    )->assertOk();

    $w = Worker::where('name', 'Tran Van A')->firstOrFail();

    expect($w->nationality)->toBe('VN')
        ->and($w->passport_no)->toBe('VN1234567')
        ->and($w->birth_date)->toBe('1992-07-01')
        ->and($w->phone_home_country)->toBe('+84 90-000-0000')
        ->and($w->email)->toBe('a@example.com')
        ->and($w->note)->toBe('딸기 경험')
        ->and($w->city->name)->toBe('당진시');
});

it('여권번호가 같으면 새로 넣지 않고 고친다', function () {
    $worker = Worker::factory()->create([
        'name' => 'Old Name',
        'passport_no' => 'UP123456',
        'note' => null,
    ]);

    $res = importWorkers(
        "이름,여권번호,비고\n"
        ."New Name,UP123456,현지 면접 통과\n"
    )->assertOk();

    expect(Worker::count())->toBe(1)
        ->and($worker->fresh()->name)->toBe('New Name')
        ->and($worker->fresh()->note)->toBe('현지 면접 통과')
        ->and($res->json('message'))->toContain('수정 1명');
});

it('번호 칸이 있으면 그 사람을 고친다', function () {
    // 엑셀 다운로드로 받은 그대로 고쳐 올리는 길. 동명이인이 있어도 안전하다.
    $a = Worker::factory()->create(['name' => '같은이름']);
    $b = Worker::factory()->create(['name' => '같은이름']);

    importWorkers("번호,이름,비고\n{$b->id},같은이름,이쪽만 수정\n")->assertOk();

    expect($b->fresh()->note)->toBe('이쪽만 수정')
        ->and($a->fresh()->note)->toBeNull();
});

it('엑셀에 없는 칸은 지우지 않는다', function () {
    // 이름과 여권번호만 적어 온 명단으로 나머지가 통째로 비워지면 안 된다.
    $worker = Worker::factory()->create([
        'passport_no' => 'KEEP1234',
        'email' => 'keep@example.com',
        'note' => '남아야 함',
    ]);

    importWorkers("여권번호,이름\nKEEP1234,이름만 변경\n")->assertOk();

    expect($worker->fresh()->name)->toBe('이름만 변경')
        ->and($worker->fresh()->email)->toBe('keep@example.com')
        ->and($worker->fresh()->note)->toBe('남아야 함');
});

it('생년월일 표기가 달라도 같은 날로 읽는다', function () {
    // 엑셀은 1990-01-02 · 1990/1/2 · 19900102 를 섞어 준다.
    importWorkers(
        "이름,국적,여권번호,생년월일\n"
        ."A,VN,AA111111,1990/1/2\n"
        ."B,VN,BB222222,19900102\n"
        ."C,VN,CC333333,1990-01-02\n"
    )->assertOk();

    expect(Worker::pluck('birth_date')->unique()->all())->toBe(['1990-01-02']);
});

it('여권번호 사이 공백은 지운다', function () {
    // 띄어쓰기 때문에 blind index 가 갈라지면 같은 사람이 두 번 등록된다.
    importWorkers("이름,국적,여권번호\nA,VN,M 123 4567\n")->assertOk();
    importWorkers("이름,여권번호,비고\nA,M1234567,두 번째\n")->assertOk();

    expect(Worker::count())->toBe(1)
        ->and(Worker::firstOrFail()->note)->toBe('두 번째');
});

it('머리글에 공백이 섞여도 같은 칸으로 읽는다', function () {
    importWorkers("이 름,국 적,여권 번호,생년 월일\nA,VN,ZZ999999,1991-05-05\n")->assertOk();

    expect(Worker::firstOrFail()->birth_date)->toBe('1991-05-05');
});

it('관리자가 아니면 근로자 목록을 못 본다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    actingAs($officer)->get(url('admin/screen/workers'))->assertForbidden();
    actingAs($officer)->postJson(route('admin.grid.workers.save'), [
        'added' => [['name' => '몰래', 'nationality' => 'VN', 'locale' => 'vi']],
    ])->assertForbidden();

    expect(Worker::count())->toBe(0);
});

it('민감 필드는 로그·배열로 새어 나가지 않는다', function () {
    // 화면에 보이는 것과 로그에 찍히는 것은 다른 문제다 (§7-1).
    $worker = Worker::factory()->create(['passport_no' => 'LEAK1234']);

    expect(json_encode($worker->toArray()))->not->toContain('LEAK1234');
});

it('새로 넣는 줄에 빠진 칸이 있으면 어느 칸인지 알려 준다', function () {
    // 'validation.required' 만 나오면 엑셀 몇백 줄에서 원인을 찾을 수 없다.
    $res = importWorkers('이름,여권번호
A,ZZ111111
')->assertStatus(422);

    expect($res->json('message'))->toContain('2행')
        ->and($res->json('message'))->toContain('국적');

    expect(Worker::count())->toBe(0);
});

it('빠진 칸이 있어도 기존 사람 수정은 막지 않는다', function () {
    // 국적은 이미 들어 있다 — 명단에 다시 안 적었다고 되돌릴 이유가 없다.
    $worker = Worker::factory()->create(['nationality' => 'LK', 'passport_no' => 'FIX11111']);

    importWorkers('여권번호,비고
FIX11111,수정만
')->assertOk();

    expect($worker->fresh()->note)->toBe('수정만')
        ->and($worker->fresh()->nationality)->toBe('LK');
});

it('다른 키로 암호화된 행이 섞여도 목록이 죽지 않는다', function () {
    // 서버와 로컬의 APP_KEY 가 다르면 실제로 이렇게 된다. 한 사람이 안 풀린다고
    // 화면 전체가 500 이 되면 나머지 27명 정보까지 못 본다.
    $good = Worker::factory()->create(['name' => '멀쩡', 'passport_no' => 'OK123456']);
    $bad = Worker::factory()->create(['name' => '못푸는사람']);

    // 다른 키로 만든 암호문을 흉내 낸다 (모양은 맞고 MAC 이 안 맞는 값).
    DB::table('workers')->where('id', $bad->id)->update([
        'passport_no' => base64_encode(json_encode([
            'iv' => base64_encode(random_bytes(16)),
            'value' => base64_encode(random_bytes(32)),
            'mac' => str_repeat('0', 64),
            'tag' => '',
        ])),
    ]);

    actingAs($this->admin);
    $rows = collect(WorkerGridController::rows());

    expect($rows)->toHaveCount(2)
        ->and($rows->firstWhere('id', $good->id)['passport_no'])->toBe('OK123456')
        // 못 푼 칸만 비고 이름·국적 같은 나머지는 그대로 보인다.
        ->and($rows->firstWhere('id', $bad->id)['passport_no'])->toBeNull()
        ->and($rows->firstWhere('id', $bad->id)['name'])->toBe('못푸는사람');
});

it('국적·지역 칸이 없는 명단은 기본값으로 채운다', function () {
    // 지자체가 주는 명단에는 한 시군·한 나라 사람만 실려 있어 그 칸을 아예 안 적는다.
    $city = City::factory()->create(['name' => '당진시']);

    actingAs($this->admin)->post(route('admin.grid.workers.import'), [
        'file' => UploadedFile::fake()->createWithContent('w.csv', "이름,성별\nMIA MD HABIB,남자 01\n"),
        'default_nationality' => 'BD',
        'default_city_id' => $city->id,
    ])->assertOk();

    $w = Worker::firstOrFail();

    expect($w->nationality)->toBe('BD')
        ->and($w->city_id)->toBe($city->id)
        // 국적을 아는 이상 알림 언어도 거기서 시작한다 (§6).
        ->and($w->locale)->toBe('bn');
});

it('파일에 적힌 값이 기본값을 이긴다', function () {
    // 명단에 다른 나라 사람이 섞여 있으면 조용히 바뀌면 안 된다.
    actingAs($this->admin)->post(route('admin.grid.workers.import'), [
        'file' => UploadedFile::fake()->createWithContent('w.csv', "이름,국적\nTran Van A,베트남\n"),
        'default_nationality' => 'BD',
    ])->assertOk();

    expect(Worker::firstOrFail()->nationality)->toBe('VN');
});

it('쪽마다 다시 나오는 머리글은 사람으로 등록하지 않는다', function () {
    // 인쇄 서식은 쪽수마다 머리글을 다시 박아 온다.
    actingAs($this->admin)->post(route('admin.grid.workers.import'), [
        'file' => UploadedFile::fake()->createWithContent('w.csv',
            "이름,성별\nA,남자 01\n이름,성별\nB,여자 02\n이름,성별\nC,남자 03\n"),
        'default_nationality' => 'BD',
    ])->assertOk();

    expect(Worker::pluck('name')->all())->toBe(['A', 'B', 'C']);
});

it('성별 뒤에 붙은 일련번호를 떼고 읽는다', function () {
    // '남자 01' 을 그대로 두면 매칭에서 성별 조건이 영영 '정보 없음' 이 된다.
    actingAs($this->admin)->post(route('admin.grid.workers.import'), [
        'file' => UploadedFile::fake()->createWithContent('w.csv',
            "이름,성별\nA,남자 01\nB,여자 03\nC,\n"),
        'default_nationality' => 'BD',
    ])->assertOk();

    expect(Worker::where('name', 'A')->firstOrFail()->gender?->value)->toBe('male')
        ->and(Worker::where('name', 'B')->firstOrFail()->gender?->value)->toBe('female')
        ->and(Worker::where('name', 'C')->firstOrFail()->gender)->toBeNull();
});

it('명단이 아닌 시트는 건너뛴다', function () {
    // 지자체 서식은 한 파일에 신청농가·근로자 리스트를 함께 담아 온다.
    // (CSV 는 시트가 하나라, 이름이 다르면 아무것도 읽지 않는 것으로 확인한다.)
    actingAs($this->admin)->post(route('admin.grid.workers.import'), [
        'file' => UploadedFile::fake()->createWithContent('w.csv', "이름,성별\nA,남자 01\n"),
        'default_nationality' => 'BD',
        'sheet' => '근로자 리스트',
    ])->assertOk();

    expect(Worker::count())->toBe(0);
});

it('근로자를 지우면 배정이 취소되어 농가 자리가 빈다', function () {
    // 사람만 지우면 '없는 사람이 배정된 자리' 가 남아 농가 정원이 계속 찬다.
    $farm = Farm::factory()->create();
    $worker = Worker::factory()->create();
    $placement = Placement::factory()->create([
        'worker_id' => $worker->id,
        'farm_id' => $farm->id,
        'status' => PlacementStatus::Confirmed,
    ]);

    $res = actingAs($this->admin)->postJson(route('admin.grid.workers.save'), [
        'deleted' => [['id' => $worker->id]],
    ])->assertOk();

    expect(Worker::find($worker->id))->toBeNull()
        ->and(Placement::find($placement->id))->toBeNull()
        // 취소를 거쳐야 왜 빠졌는지가 남는다.
        ->and(Placement::withTrashed()->findOrFail($placement->id)->status)
        ->toBe(PlacementStatus::Cancelled)
        ->and($res->json('message'))->toContain('근로자 1명 삭제');

    // 농가 쪽에서 자리가 실제로 비었는지.
    $row = collect(MatchingController::farmRows())->firstWhere('id', $farm->id);
    expect($row['placed'])->toBe(0);
});

it('근로자를 지우면 딸린 자료도 함께 정리된다', function () {
    $worker = Worker::factory()->create();
    SupportTicket::factory()->create(['worker_id' => $worker->id]);

    actingAs($this->admin)->postJson(route('admin.grid.workers.save'), [
        'deleted' => [['id' => $worker->id]],
    ])->assertOk();

    expect(DB::table('support_tickets')->where('worker_id', $worker->id)->count())->toBe(0);
});

it('근로 기간을 목록에 담고 저장한다', function () {
    $worker = Worker::factory()->create([
        'work_start_date' => '2026-04-07',
        'work_end_date' => '2026-07-06',
    ]);

    actingAs($this->admin);
    $row = collect(WorkerGridController::rows())->firstWhere('id', $worker->id);

    expect($row['work_start_date'])->toBe('2026-04-07')
        ->and($row['work_end_date'])->toBe('2026-07-06');
});

it('엑셀의 개월수로 종료일을 계산한다', function () {
    // 지자체 명단은 종료일 대신 '3개월' 만 적어 오고, 출국일 칸은 수식이라 비어 온다.
    actingAs($this->admin)->post(route('admin.grid.workers.import'), [
        'file' => UploadedFile::fake()->createWithContent('w.csv',
            "이름,입국일,근로 기간\nA,2026-04-07,3개월\nB,2026-02-24,5개월\nC,2026-04-07,8개월\n"),
        'default_nationality' => 'BD',
    ])->assertOk();

    // EDATE(입국일, 개월수) - 1 — 그 달의 같은 날 하루 전까지.
    expect(Worker::where('name', 'A')->firstOrFail()->work_end_date->toDateString())->toBe('2026-07-06')
        ->and(Worker::where('name', 'B')->firstOrFail()->work_end_date->toDateString())->toBe('2026-07-23')
        ->and(Worker::where('name', 'C')->firstOrFail()->work_end_date->toDateString())->toBe('2026-12-06');
});

it('종료일이 적혀 있으면 개월수보다 그쪽을 따른다', function () {
    actingAs($this->admin)->post(route('admin.grid.workers.import'), [
        'file' => UploadedFile::fake()->createWithContent('w.csv',
            "이름,입국일,근로 기간,출국일\nA,2026-04-07,3개월,2026-06-30\n"),
        'default_nationality' => 'BD',
    ])->assertOk();

    expect(Worker::firstOrFail()->work_end_date->toDateString())->toBe('2026-06-30');
});

it('종료일이 시작일보다 앞서면 막는다', function () {
    // 그대로 두면 목록에서 기간이 음수로 보이고 만료 알림이 영영 안 뜬다.
    $res = saveWorkers([[
        'name' => 'A', 'nationality' => 'BD', 'locale' => 'bn',
        'work_start_date' => '2026-07-01', 'work_end_date' => '2026-04-01',
    ]])->assertStatus(422);

    expect($res->json('message'))->toContain('근로 종료')
        ->and(Worker::count())->toBe(0);
});

it('근로 기간은 배정 기간과 따로 간다', function () {
    // 한 사람이 A 농가 두 달, B 농가 한 달 일할 수 있다. 체류 기간은 그대로다.
    $worker = Worker::factory()->create([
        'work_start_date' => '2026-04-07',
        'work_end_date' => '2026-12-06',
    ]);
    Placement::factory()->create([
        'worker_id' => $worker->id,
        'start_date' => '2026-04-07',
        'end_date' => '2026-07-06',
    ]);

    expect($worker->fresh()->work_end_date->toDateString())->toBe('2026-12-06')
        ->and($worker->placements()->first()->end_date->toDateString())->toBe('2026-07-06');
});
