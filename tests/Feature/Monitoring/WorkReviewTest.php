<?php

declare(strict_types=1);

use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Monitoring\Actions\RecordWorkReviewAction;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Enums\WorkReviewResult;
use App\Domains\Monitoring\Enums\WorkReviewSection;
use App\Domains\Monitoring\Enums\WorkReviewType;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Monitoring\Models\WorkReviewItem;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Admin\WorkReviewController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkReviewItemSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * 외국인근로자 근무상태 종합 점검표 (원본 work-status-review.docx).
 *
 * 점검자가 근로자 한 사람을 현장에서 점검한다. 응답으로 이탈 리스크를 산출하며
 * 위치 추적을 쓰지 않는다(§7-2). 농가·근로자 인적사항은 점검표에 복사하지 않는다.
 */
function reviewInspector(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);

    return $admin;
}

/** 확정 배정을 가진 근로자 — 점검표는 배정된 농가에 붙는다. */
function placedWorker(): Worker
{
    $worker = Worker::factory()->create(['status' => WorkerStatus::Active->value]);
    Placement::factory()->create([
        'worker_id' => $worker->id,
        'farm_id' => Farm::factory(),
        'status' => PlacementStatus::Confirmed->value,
    ]);

    return $worker;
}

/** 모든 활성 항목에 '좋음' 으로 답한 응답표. */
function allGoodAnswers(): array
{
    return WorkReviewItem::query()->active()->get()
        ->mapWithKeys(fn (WorkReviewItem $i) => [
            $i->id => $i->section->isRating() ? 'high' : ($i->adverse ? 'no' : 'yes'),
        ])
        ->all();
}

function baseReviewData(): array
{
    return [
        'reviewed_at' => now()->toDateTimeString(),
        'review_type' => WorkReviewType::Regular->value,
        'result' => WorkReviewResult::Good->value,
    ];
}

it('시더가 점검 항목 43가지를 영역별로 만든다', function () {
    $this->seed(WorkReviewItemSeeder::class);

    expect(WorkReviewItem::count())->toBe(43);
    expect(WorkReviewItem::where('section', WorkReviewSection::Attendance->value)->count())->toBe(8);
    expect(WorkReviewItem::where('section', WorkReviewSection::Performance->value)->count())->toBe(13);
    expect(WorkReviewItem::where('section', WorkReviewSection::Community->value)->count())->toBe(10);
    expect(WorkReviewItem::where('section', WorkReviewSection::Safety->value)->count())->toBe(12);

    // 확인된 쪽이 문제인 항목은 방향이 뒤집혀 있어야 한다.
    expect(WorkReviewItem::where('code', 'safety_wage_arrears')->first()->adverse)->toBeTrue();
    expect(WorkReviewItem::where('code', 'safety_training')->first()->adverse)->toBeFalse();
    // 좋고 나쁨이 아닌 항목은 점수에 넣지 않는다.
    expect(WorkReviewItem::where('code', 'safety_recent_hospital')->first()->scored)->toBeFalse();
});

it('시더를 다시 돌려도 항목이 늘지 않고 내려 둔 항목이 켜지지 않는다', function () {
    $this->seed(WorkReviewItemSeeder::class);
    WorkReviewItem::where('code', 'perf_machinery')->update(['active' => false]);

    $this->seed(WorkReviewItemSeeder::class);

    expect(WorkReviewItem::count())->toBe(43);
    expect(WorkReviewItem::where('code', 'perf_machinery')->first()->active)->toBeFalse();
});

it('전부 양호하면 리스크가 낮음이다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), allGoodAnswers());

    expect($review->risk_score)->toBe(0);
    expect($review->risk_level)->toBe(RiskLevel::Low);
    expect($review->answers()->count())->toBe(43);
});

it('나쁜 응답은 2점, 보통은 1점으로 쌓인다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $answers = allGoodAnswers();
    $bad = WorkReviewItem::where('code', 'perf_speed')->first();
    $mid = WorkReviewItem::where('code', 'perf_focus')->first();
    $answers[$bad->id] = 'low';
    $answers[$mid->id] = 'mid';

    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), $answers);

    expect($review->risk_score)->toBe(3);
    expect($review->risk_level)->toBe(RiskLevel::Medium);
});

it('이탈 가능성이 미흡이면 점수와 무관하게 고위험이다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $answers = allGoodAnswers();
    $answers[WorkReviewItem::where('code', WorkReviewItem::FLIGHT_RISK)->first()->id] = 'low';

    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), $answers);

    // 점수만 보면 '주의' 문턱에도 못 미친다.
    expect($review->risk_score)->toBe(2);
    expect($review->risk_level)->toBe(RiskLevel::High);
});

it('임금 체불과 특별관리 대상은 그 자체로 고위험이다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);
    $inspector = reviewInspector();

    $unpaid = app(RecordWorkReviewAction::class)->execute(
        placedWorker(), $inspector, [...baseReviewData(), 'wage_unpaid' => true], allGoodAnswers(),
    );
    expect($unpaid->risk_level)->toBe(RiskLevel::High);

    $special = app(RecordWorkReviewAction::class)->execute(
        placedWorker(), $inspector,
        [...baseReviewData(), 'result' => WorkReviewResult::SpecialCare->value],
        allGoodAnswers(),
    );
    expect($special->risk_level)->toBe(RiskLevel::High);
});

it('확인·미확인 항목은 방향에 따라 반대로 읽는다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);
    $inspector = reviewInspector();

    // 안전교육 '미확인' 은 나쁜 신호다.
    $answers = allGoodAnswers();
    $answers[WorkReviewItem::where('code', 'safety_training')->first()->id] = 'no';
    expect(app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), $inspector, baseReviewData(), $answers)->risk_score)->toBe(2);

    // 건강 이상 '확인' 도 나쁜 신호다 — 방향이 반대인 항목.
    $answers = allGoodAnswers();
    $answers[WorkReviewItem::where('code', 'safety_health_issue')->first()->id] = 'yes';
    expect(app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), $inspector, baseReviewData(), $answers)->risk_score)->toBe(2);
});

it('좋고 나쁨이 아닌 항목은 어느 쪽으로 답해도 점수에 들어가지 않는다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $answers = allGoodAnswers();
    $answers[WorkReviewItem::where('code', 'safety_recent_hospital')->first()->id] = 'no';

    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), $answers);

    expect($review->risk_score)->toBe(0);
    // 점수에는 안 들어가도 응답은 남는다.
    expect($review->answers()->count())->toBe(43);
});

it('배정이 없는 근로자는 점검표를 만들 수 없다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $orphan = Worker::factory()->create(['status' => WorkerStatus::Active->value]);

    expect(fn () => app(RecordWorkReviewAction::class)
        ->execute($orphan, reviewInspector(), baseReviewData(), allGoodAnswers()))
        ->toThrow(RuntimeException::class);
});

it('꺼진 항목이나 그 영역에 없는 보기는 버린다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $off = WorkReviewItem::where('code', 'perf_machinery')->first();
    $off->update(['active' => false]);

    $answers = allGoodAnswers();
    $answers[$off->id] = 'low';
    // 3단계 영역에 확인·미확인 보기를 보내면 무시한다.
    $answers[WorkReviewItem::where('code', 'perf_speed')->first()->id] = 'yes';

    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), $answers);

    expect($review->risk_score)->toBe(0);
    expect($review->answers()->count())->toBe(41);
});

it('월 평균 임금은 암호화해 저장한다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $review = app(RecordWorkReviewAction::class)->execute(
        placedWorker(), reviewInspector(),
        [...baseReviewData(), 'avg_monthly_wage' => '1,800,000원'],
        allGoodAnswers(),
    );

    expect($review->avg_monthly_wage)->toBe('1,800,000원');
    // DB 에는 평문이 남지 않는다 (§7-1).
    $raw = DB::table('work_reviews')->where('id', $review->id)->value('avg_monthly_wage');
    expect($raw)->not->toContain('1,800,000');
});

it('점검표에는 위치 컬럼이 없다', function () {
    // §7-2: 근로자 위치는 SOS 와 점검자 체크인 두 곳에만 존재한다.
    $columns = Schema::getColumnListing('work_reviews');

    expect($columns)->not->toContain('lat')->not->toContain('lng');
});

it('콘솔에서 점검표를 작성하고 목록에서 볼 수 있다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $worker = placedWorker();
    $admin = reviewInspector();

    $this->actingAs($admin)
        ->postJson(route('admin.work-reviews.store'), [
            ...baseReviewData(),
            'worker_id' => $worker->id,
            'place' => '농가 작업장',
            'answers' => allGoodAnswers(),
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(WorkReview::where('worker_id', $worker->id)->count())->toBe(1);

    $rows = WorkReviewController::rows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['worker'])->toBe($worker->name);
    expect($rows[0]['risk'])->toBe('낮음');

    $this->actingAs($admin)->get('/admin/screen/work-reviews')
        ->assertOk()
        ->assertSee('근무상태 종합 점검표');
});

it('콘솔 상세에 영역별 응답이 나오고 나쁜 응답이 표시된다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $answers = allGoodAnswers();
    $answers[WorkReviewItem::where('code', 'comm_alcohol')->first()->id] = 'low';

    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), $answers);

    $res = $this->actingAs(reviewInspector())
        ->getJson(route('admin.work-reviews.show', $review))
        ->assertOk();

    $community = collect($res->json('sections'))->firstWhere('label', '협동 및 생활관리');
    $alcohol = collect($community['answers'])->firstWhere('label', '음주 관련 문제');

    expect($alcohol['value'])->toBe('미흡');
    expect($alcohol['bad'])->toBeTrue();
});

it('관리자 앱이 항목표를 받아 점검표를 올린다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $worker = placedWorker();
    $admin = reviewInspector();
    Sanctum::actingAs($admin, ['*']);

    $form = $this->getJson('/api/v1/admin/work-reviews/form')->assertOk();
    expect($form->json('data'))->toHaveCount(4);
    expect($form->json('data.0.items'))->toHaveCount(8);
    expect($form->json('data.3.options'))->toHaveCount(2);   // 확인 / 미확인

    $this->postJson('/api/v1/admin/work-reviews', [
        ...baseReviewData(),
        'worker_id' => $worker->id,
        'answers' => allGoodAnswers(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.risk_level', 'low');

    $this->getJson('/api/v1/admin/work-reviews')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('시청 담당자는 점검표를 작성할 수 없다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);

    $worker = placedWorker();
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);
    Sanctum::actingAs($officer, ['*']);

    $this->postJson('/api/v1/admin/work-reviews', [
        ...baseReviewData(),
        'worker_id' => $worker->id,
        'answers' => [],
    ])->assertForbidden();
});
