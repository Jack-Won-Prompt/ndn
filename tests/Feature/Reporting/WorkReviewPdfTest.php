<?php

declare(strict_types=1);

use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Monitoring\Actions\RecordWorkReviewAction;
use App\Domains\Monitoring\Models\WorkReviewItem;
use App\Domains\Reporting\Actions\GenerateWorkReviewPdfAction;
use App\Shared\Support\SignatureImage;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkReviewItemSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * 근무상태 종합 점검표 제출용 PDF.
 *
 * 원본 각주: "관할 지자체 및 관계기관의 요청 시 제출할 수 있습니다."
 * 제출이 이 서식의 존재 이유라, 화면으로만 두면 반쪽이다.
 *
 * 관공서 제출 서식이라 여권번호·생년월일이 들어간다. 대신 파일로 저장하지 않고
 * (§7-1 암호화 필드를 평문으로 쌓아 두지 않는다) 내려받을 때 기록을 남긴다(§7-6).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);
});

it('PDF 를 만든다', function () {
    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), allGoodAnswers());

    $output = app(GenerateWorkReviewPdfAction::class)->pdf($review)->output();

    expect(substr($output, 0, 4))->toBe('%PDF');
});

it('관공서 제출에 필요한 인적사항이 들어간다', function () {
    // 이 값들이 비면 제출이 되지 않는다. 암호화 필드라 복호화해 싣는다(§7-1).
    $worker = placedWorker();
    $worker->forceFill([
        'passport_no' => 'M12345678',
        'birth_date' => '1995-03-14',
        'phone_home_country' => '+84-90-1234-5678',
    ])->save();

    $data = app(GenerateWorkReviewPdfAction::class)->data(
        app(RecordWorkReviewAction::class)
            ->execute($worker->refresh(), reviewInspector(), baseReviewData(), allGoodAnswers())
    );

    expect($data['passport_no'])->toBe('M12345678')
        ->and($data['birth_date'])->toBe('1995-03-14')
        ->and($data['phone_home'])->toBe('+84-90-1234-5678')
        ->and($data['age'])->toBeInt();
});

it('농가·계약기간·입국일을 관계에서 이어 붙인다', function () {
    // 점검표에 복사해 두지 않은 값들이다.
    $worker = placedWorker();
    $placement = $worker->currentPlacement();
    $placement->update(['start_date' => '2026-03-01', 'end_date' => '2026-08-31']);
    ArrivalRecord::create([
        'placement_id' => $placement->id,
        'status' => ArrivalStatus::Arrived,
        'arrived_at' => '2026-02-28 09:00:00',
    ]);

    $data = app(GenerateWorkReviewPdfAction::class)->data(
        app(RecordWorkReviewAction::class)
            ->execute($worker, reviewInspector(), baseReviewData(), allGoodAnswers())
    );

    expect($data['farm']?->id)->toBe($placement->farm_id)
        ->and($data['contract_from']?->format('Y-m-d'))->toBe('2026-03-01')
        ->and($data['contract_to']?->format('Y-m-d'))->toBe('2026-08-31')
        ->and($data['entered_on'])->not->toBeNull();
});

it('응답하지 않은 항목도 빈칸으로 남는다', function () {
    // 종이 서식과 항목 수가 같아야 제출본으로 쓸 수 있다.
    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), []);

    $data = app(GenerateWorkReviewPdfAction::class)->data($review);

    $rows = collect($data['sections'])->flatMap(fn ($s) => $s['rows']);

    expect($rows)->toHaveCount(WorkReviewItem::query()->active()->count())
        ->and($rows->every(fn ($r) => $r['value'] === null))->toBeTrue();
});

it('서명 이미지를 PDF 안에 심는다', function () {
    // private 저장이라 URL 로는 못 가져온다. 빠지면 다시 증빙이 되지 않는다.
    Storage::fake(SignatureImage::DISK);

    $review = app(RecordWorkReviewAction::class)->execute(
        placedWorker(), reviewInspector(), baseReviewData(), allGoodAnswers(),
        ['worker' => signaturePng()],
    );

    $data = app(GenerateWorkReviewPdfAction::class)->data($review);
    $signs = collect($data['signatures'])->keyBy('label');

    expect($signs['외국인근로자']['image'])->toStartWith('data:image/png;base64,');
    // 서명하지 않은 칸은 비어 있다.
    expect($signs['농가 대표']['image'])->toBeNull();
});

it('내려받으면 열람 기록이 남는다', function () {
    $worker = placedWorker();
    $admin = reviewInspector();
    $review = app(RecordWorkReviewAction::class)
        ->execute($worker, $admin, baseReviewData(), allGoodAnswers());

    $res = actingAs($admin)->get(route('admin.work-reviews.pdf', $review))->assertOk();

    expect($res->headers->get('content-type'))->toContain('application/pdf');
    // 파일명만 봐도 누구의 언제 점검인지 안다.
    expect(rawurldecode((string) $res->headers->get('content-disposition')))
        ->toContain('근무상태 점검표')->toContain($worker->name);

    // §7-6: 인적사항이 담긴 문서를 뽑았으니 기록한다.
    $log = Activity::where('log_name', 'personal-data-access')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->properties['reason'])->toBe('work-review-pdf')
        ->and($log->subject_id)->toBe($worker->id);
});

it('로그인하지 않으면 받을 수 없다', function () {
    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), allGoodAnswers());

    $this->get(route('admin.work-reviews.pdf', $review))->assertRedirect();

    expect(Activity::where('log_name', 'personal-data-access')->count())->toBe(0);
});

it('PDF 를 파일로 저장해 두지 않는다', function () {
    // 저장하면 암호화 필드를 평문으로 복사해 쌓아 두는 셈이 된다(§7-1).
    Storage::fake('local');

    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), allGoodAnswers());

    actingAs(reviewInspector())->get(route('admin.work-reviews.pdf', $review))->assertOk();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});
