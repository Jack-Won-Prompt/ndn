<?php

declare(strict_types=1);

use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Reporting\Actions\ShareWorkReviewsAction;
use App\Domains\Reporting\Models\WorkReviewShare;
use App\Http\Controllers\Admin\WorkReviewController;
use App\Mail\WorkReviewShareMail;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * 근무상태 점검표 관계기관 제출 (업무흐름 §6).
 *
 * 서식 각주가 "관할 지자체 및 관계기관의 요청 시 제출" 이라, 제출 자체가 목적이다.
 * 그래서 인적사항이 든 PDF 를 밖으로 내보낸다. 대신 **메일 본문·첨부 파일명에는**
 * 넣지 않고(§7-3), 누구에게 나갔는지를 남긴다(§7-6). 그 두 갈래를 여기서 지킨다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Mail::fake();

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);

    $this->worker = Worker::factory()->create([
        'name' => '응우옌티하',
        'passport_no' => 'M1234567',
    ]);

    $this->review = WorkReview::factory()->create([
        'worker_id' => $this->worker->id,
        'reviewed_at' => now()->subDays(3),
    ]);
});

function shareBody(array $override = []): array
{
    return array_merge([
        'review_ids' => [test()->review->id],
        'recipients' => [['org' => '당진시청', 'email' => 'nongjeong@dangjin.go.kr']],
        'note' => '요청하신 점검 결과를 제출합니다.',
        'acknowledged' => 1,
    ], $override);
}

it('선택한 점검표를 PDF 로 첨부해 보낸다', function () {
    actingAs($this->admin)
        ->postJson(route('admin.work-reviews.share'), shareBody())
        ->assertOk()
        ->assertJsonPath('reviews', 1)
        ->assertJsonPath('recipients', 1);

    Mail::assertSent(WorkReviewShareMail::class, function (WorkReviewShareMail $mail) {
        return $mail->hasTo('nongjeong@dangjin.go.kr') && $mail->count === 1;
    });
});

it('메일 본문과 첨부 파일명에 개인정보가 없다', function () {
    // 첨부 PDF 안에는 여권번호가 들어간다(제출 서식). 밖으로 드러나는 글자는 달라야 한다.
    actingAs($this->admin)->postJson(route('admin.work-reviews.share'), shareBody())->assertOk();

    Mail::assertSent(WorkReviewShareMail::class, function (WorkReviewShareMail $mail) {
        $text = implode(' ', $mail->outboundStrings());

        expect($text)->not->toContain('응우옌티하')
            ->and($text)->not->toContain('M1234567');

        // 파일명은 메일 목록에 그대로 뜬다 — 번호로만 구분한다.
        expect($mail->documents[0]['name'])->toBe(
            '근무상태점검표_'.now()->subDays(3)->timezone(config('ndn.timezone'))->format('Ymd').'_no'.$this->review->id.'.pdf'
        );

        return true;
    });
});

it('안내 문구에 개인정보를 적으면 거부한다', function () {
    // 본문은 로그인 없이 도달한다(§7-3). 자유 입력칸이 구멍이 되기 쉽다.
    actingAs($this->admin)
        ->postJson(route('admin.work-reviews.share'), shareBody([
            'note' => '담당자 010-1234-5678 로 연락 주세요.',
        ]))
        ->assertStatus(422);

    Mail::assertNothingSent();
    expect(WorkReviewShare::count())->toBe(0);
});

it('보낸 기록이 점검표·수신처별로 남는다', function () {
    $second = WorkReview::factory()->create(['worker_id' => $this->worker->id]);

    actingAs($this->admin)
        ->postJson(route('admin.work-reviews.share'), shareBody([
            'review_ids' => [$this->review->id, $second->id],
            'recipients' => [
                ['org' => '당진시청', 'email' => 'a@dangjin.go.kr'],
                ['org' => '출입국', 'email' => 'b@immigration.go.kr'],
            ],
        ]))
        ->assertOk();

    // 점검표 2건 × 수신처 2곳 = 4줄. 어느 점검표가 어디로 갔는지 되짚을 수 있어야 한다.
    expect(WorkReviewShare::count())->toBe(4)
        ->and(WorkReviewShare::distinct()->count('batch_id'))->toBe(1)
        ->and(WorkReviewShare::where('work_review_id', $second->id)->pluck('recipient_email')->sort()->values()->all())
        ->toBe(['a@dangjin.go.kr', 'b@immigration.go.kr']);
});

it('제출하면 열람 기록이 남는다', function () {
    // 인적사항이 담긴 문서가 밖으로 나간 사건이다(§7-6).
    actingAs($this->admin)->postJson(route('admin.work-reviews.share'), shareBody())->assertOk();

    $access = Activity::where('log_name', 'personal-data-access')->latest('id')->first();
    expect($access)->not->toBeNull()
        ->and($access->properties['reason'])->toBe('work-review-share')
        ->and($access->subject_id)->toBe($this->worker->id);

    $share = Activity::where('log_name', 'work-review-share')->latest('id')->first();
    expect($share)->not->toBeNull()
        ->and($share->properties['recipients'])->toBe(['nongjeong@dangjin.go.kr']);
});

it('제출 근거 확인을 체크하지 않으면 보내지 않는다', function () {
    actingAs($this->admin)
        ->postJson(route('admin.work-reviews.share'), shareBody(['acknowledged' => 0]))
        ->assertStatus(422);

    Mail::assertNothingSent();
});

it('한 번에 보낼 수 있는 건수를 넘기면 막는다', function () {
    // PDF 를 그 자리에서 만든다. 상한이 없으면 요청이 죽는다.
    $ids = collect(range(1, ShareWorkReviewsAction::MAX_REVIEWS + 1))
        ->map(fn () => WorkReview::factory()->create(['worker_id' => $this->worker->id])->id)
        ->all();

    actingAs($this->admin)
        ->postJson(route('admin.work-reviews.share'), shareBody(['review_ids' => $ids]))
        ->assertStatus(422)
        ->assertJsonPath('message', '한 번에 '.ShareWorkReviewsAction::MAX_REVIEWS.'건까지 보낼 수 있습니다. 나눠서 보내 주세요.');

    Mail::assertNothingSent();
});

it('관리자가 아니면 제출할 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    actingAs($officer)->postJson(route('admin.work-reviews.share'), shareBody())->assertForbidden();

    Mail::assertNothingSent();
});

it('제출 이력이 발송 묶음·수신처 단위로 접혀 나온다', function () {
    $second = WorkReview::factory()->create(['worker_id' => $this->worker->id]);

    actingAs($this->admin)->postJson(route('admin.work-reviews.share'), shareBody([
        'review_ids' => [$this->review->id, $second->id],
    ]))->assertOk();

    $rows = WorkReviewController::shareRows();

    // 4줄이 아니라 '한 곳에 2건 보냄' 한 줄로 보여야 읽힌다.
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['count'])->toBe(2)
        ->and($rows[0]['org'])->toBe('당진시청');
});
