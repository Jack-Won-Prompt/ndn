<?php

declare(strict_types=1);

use App\Domains\Monitoring\Actions\RecordWorkReviewAction;
use App\Domains\Monitoring\Models\WorkReview;
use App\Shared\Support\SignatureImage;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkReviewItemSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * 근무상태 종합 점검표 §12 «확인 및 서명» — 서명 이미지.
 *
 * 이 점검표는 관할 지자체·출입국이 요청하면 제출하는 자료다(원본 각주).
 * 이름만 적힌 표는 증빙이 되지 않으므로 서명을 파일로 받는다.
 * 서명은 본인을 특정하는 개인정보라 private 저장 + 인증 라우트로만 나간다(§9).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(WorkReviewItemSeeder::class);
    Storage::fake('local');
});

/** 진짜 PNG 한 장 (1x1). 매직 넘버 검사를 통과해야 한다. */
function signaturePng(): string
{
    return 'data:image/png;base64,'.base64_encode(
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
    );
}

it('서명한 칸만 파일로 저장한다', function () {
    $review = app(RecordWorkReviewAction::class)->execute(
        placedWorker(), reviewInspector(), baseReviewData(), allGoodAnswers(),
        // 통역인은 해당할 때만 서명한다 — 빈 칸은 그대로 비워 둔다.
        ['inspector' => signaturePng(), 'worker' => signaturePng()],
    );

    expect($review->hasSignature('inspector'))->toBeTrue()
        ->and($review->hasSignature('worker'))->toBeTrue()
        ->and($review->hasSignature('farm'))->toBeFalse()
        ->and($review->hasSignature('interpreter'))->toBeFalse()
        ->and($review->signatureCount())->toBe(2);

    // 점검표 아래에 모아 두고 경로만 DB 에 남긴다.
    expect($review->signaturePath('worker'))->toStartWith('work-reviews/'.$review->id.'/signatures/');
    Storage::disk('local')->assertExists($review->signaturePath('worker'));
});

it('서명이 없어도 점검표는 저장된다', function () {
    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), reviewInspector(), baseReviewData(), allGoodAnswers());

    expect($review->signatureCount())->toBe(0);
});

it('그리지 않은 빈 서명은 받지 않는다', function () {
    // 캔버스를 건드리지 않아도 data URL 은 만들어진다. 빈 값을 저장하면
    // '서명 받음'으로 보여 증빙이 아닌 것이 증빙이 된다.
    expect(SignatureImage::decode(null))->toBeNull()
        ->and(SignatureImage::decode(''))->toBeNull()
        ->and(SignatureImage::decode('data:image/png;base64,'))->toBeNull();
});

it('이미지가 아닌 바이트는 서명으로 받지 않는다', function () {
    expect(SignatureImage::decode('data:image/png;base64,'.base64_encode('not an image')))->toBeNull();
});

it('PNG 와 JPEG 를 모두 받고 확장자를 맞춰 저장한다', function () {
    // 앱이 사진으로 올릴 수 있다. PNG 만 받으면 그 서명이 조용히 사라진다.
    $jpeg = "\xFF\xD8\xFF".str_repeat("\x00", 32);

    $png = SignatureImage::store(signaturePng(), 'sig-test', 'a');
    $jpg = SignatureImage::store('data:image/jpeg;base64,'.base64_encode($jpeg), 'sig-test', 'b');

    expect($png)->toEndWith('.png')
        ->and($jpg)->toEndWith('.jpg')
        ->and(SignatureImage::mime($jpg))->toBe('image/jpeg')
        ->and(SignatureImage::mime($png))->toBe('image/png');
});

it('지나치게 큰 파일은 서명으로 받지 않는다', function () {
    // 서명 캔버스 PNG 는 수십 KB 다. 이보다 크면 사진이거나 장난이다.
    $huge = "\x89PNG\r\n\x1a\n".str_repeat('x', SignatureImage::MAX_BYTES + 1);

    expect(SignatureImage::decode(base64_encode($huge)))->toBeNull();
});

it('다시 서명하면 예전 서명 파일을 덮어쓰지 않는다', function () {
    $a = SignatureImage::store(signaturePng(), 'sig-test', 'worker');
    $b = SignatureImage::store(signaturePng(), 'sig-test', 'worker');

    // 무엇에 서명했는지가 증빙이라 예전 파일도 남아 있어야 한다.
    expect($a)->not->toBe($b);
    Storage::disk('local')->assertExists($a);
    Storage::disk('local')->assertExists($b);
});

it('콘솔에서 서명과 함께 저장하고 상세에서 이미지 주소를 받는다', function () {
    $worker = placedWorker();
    $admin = reviewInspector();

    actingAs($admin)
        ->postJson(route('admin.work-reviews.store'), [
            ...baseReviewData(),
            'worker_id' => $worker->id,
            'signed_worker' => '응우옌',
            'answers' => allGoodAnswers(),
            'signatures' => ['worker' => signaturePng()],
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $review = WorkReview::where('worker_id', $worker->id)->firstOrFail();

    $res = actingAs($admin)->getJson(route('admin.work-reviews.show', $review))->assertOk();

    $signs = collect($res->json('signatures'))->keyBy('role');
    expect($signs['worker']['name'])->toBe('응우옌')
        ->and($signs['worker']['image_url'])->toContain('/signature/worker');
    // 서명하지 않은 칸은 주소가 없어 화면에서 '서명 없음'으로 드러난다.
    expect($signs['farm']['image_url'])->toBeNull();
});

it('서명 이미지는 인증된 라우트로만 나가고 근로자 서명은 열람 기록을 남긴다', function () {
    $worker = placedWorker();
    $admin = reviewInspector();

    $review = app(RecordWorkReviewAction::class)->execute(
        $worker, $admin, baseReviewData(), allGoodAnswers(),
        ['worker' => signaturePng()],
    );

    $url = route('admin.work-reviews.signature', [$review, 'worker']);

    // 로그인하지 않으면 볼 수 없다.
    $this->get($url)->assertRedirect();

    actingAs($admin)->get($url)->assertOk()->assertHeader('content-type', 'image/png');

    // §7-6: 근로자 개인정보 열람은 기록한다.
    $log = Activity::where('log_name', 'personal-data-access')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->properties['reason'])->toBe('work-review-signature')
        ->and($log->subject_id)->toBe($worker->id);
});

it('없는 서명이나 없는 역할은 404 다', function () {
    $admin = reviewInspector();
    $review = app(RecordWorkReviewAction::class)
        ->execute(placedWorker(), $admin, baseReviewData(), allGoodAnswers());

    actingAs($admin)->get(route('admin.work-reviews.signature', [$review, 'worker']))->assertNotFound();
    actingAs($admin)->get(url('admin/work-reviews/'.$review->id.'/signature/bogus'))->assertNotFound();
});
