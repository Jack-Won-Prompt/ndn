<?php

declare(strict_types=1);

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\WorkerGuide;
use App\Models\Setting;
use Database\Seeders\WorkerGuideSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * 근로자 안내 자료 (사전교육·긴급 연락처) — 앱 정보 화면.
 *
 * 원본 docx 는 그대로 두고, 앱이 화면으로 그릴 수 있게 구조화한 내용을 내보낸다.
 * 한국어 원문 하나만 두고 근로자 언어로 실시간 번역한다 (CLAUDE.md §6).
 */
function guideWorker(string $locale = 'ko'): Worker
{
    $worker = Worker::factory()->create(['locale' => $locale]);
    Sanctum::actingAs($worker, ['*']);

    return $worker;
}

it('시더가 사전교육·긴급 연락처 자료를 만든다', function () {
    $this->seed(WorkerGuideSeeder::class);

    $guide = WorkerGuide::where('key', 'pre-training')->firstOrFail();
    expect($guide->sections)->not->toBeEmpty();

    // 생활비 표는 이 자료가 유일한 원본이다.
    $costs = $guide->sections->firstWhere('payload.heading', '한국 생활비 안내 (예상)');
    expect($costs)->not->toBeNull()
        ->and($costs->type)->toBe('table')
        ->and($costs->payload['rows'])->toHaveCount(8);

    expect(WorkerGuide::where('key', 'emergency')->exists())->toBeTrue();
});

it('시더를 다시 돌려도 섹션이 중복되지 않는다', function () {
    $this->seed(WorkerGuideSeeder::class);
    $before = WorkerGuide::where('key', 'pre-training')->firstOrFail()->sections()->count();

    $this->seed(WorkerGuideSeeder::class);

    expect(WorkerGuide::where('key', 'pre-training')->firstOrFail()->sections()->count())->toBe($before);
});

it('운영에서 내려 둔 자료는 시더를 다시 돌려도 켜지지 않는다', function () {
    $this->seed(WorkerGuideSeeder::class);
    WorkerGuide::where('key', 'emergency')->update(['active' => false]);

    $this->seed(WorkerGuideSeeder::class);

    expect(WorkerGuide::where('key', 'emergency')->firstOrFail()->active)->toBeFalse();
});

it('목록은 제목만 주고 본문은 주지 않는다', function () {
    guideWorker();
    $this->seed(WorkerGuideSeeder::class);

    $res = $this->getJson('/api/v1/guides')->assertOk();

    expect($res->json('data'))->toHaveCount(2);
    expect($res->json('data.0'))->toHaveKeys(['key', 'title', 'lead', 'icon'])
        ->and($res->json('data.0'))->not->toHaveKey('sections');
    expect($res->json('data.0.key'))->toBe('pre-training');
});

it('자료를 열면 섹션이 순서대로 나온다', function () {
    guideWorker();
    $this->seed(WorkerGuideSeeder::class);

    $res = $this->getJson('/api/v1/guides/pre-training')->assertOk();

    $types = collect($res->json('data.sections'))->pluck('type');
    expect($types->first())->toBe('text')
        ->and($types->unique()->diff(['text', 'list', 'table', 'qa', 'contacts', 'steps']))->toBeEmpty();

    // FAQ 는 질문·답변 쌍으로 나간다.
    $qa = collect($res->json('data.sections'))->firstWhere('type', 'qa');
    expect($qa['payload']['items'][0])->toHaveKeys(['q', 'a']);
});

it('연락처 자리에 콘솔 설정값이 채워진다', function () {
    Setting::put('company.phone', '031-000-0000');
    guideWorker();
    $this->seed(WorkerGuideSeeder::class);

    $res = $this->getJson('/api/v1/guides/emergency')->assertOk();

    $desk = collect($res->json('data.sections'))
        ->firstWhere('payload.heading', 'NDN KOREA 한국 데스크');

    $values = collect($desk['payload']['items'])->pluck('value', 'label');
    expect($values['대표번호'])->toBe('031-000-0000');
    // 아직 입력하지 않은 값은 자리 표시자가 그대로 새어 나가지 않는다.
    expect($values['이메일'])->toBe('—');
});

it('꺼 둔 자료는 목록과 상세 모두에서 빠진다', function () {
    guideWorker();
    $this->seed(WorkerGuideSeeder::class);
    WorkerGuide::where('key', 'emergency')->update(['active' => false]);

    $res = $this->getJson('/api/v1/guides')->assertOk();
    expect(collect($res->json('data'))->pluck('key'))->not->toContain('emergency');

    $this->getJson('/api/v1/guides/emergency')->assertNotFound();
});

it('근로자 언어로 번역해서 나가되 전화번호는 건드리지 않는다', function () {
    // 번역기는 외부 호출이므로 가짜로 세운다 — 줄 단위로 표시만 붙여 돌려준다.
    Http::fake(function ($request) {
        $q = (string) ($request->data()['q'] ?? '');
        $out = collect(explode("\n", $q))->map(fn ($l) => 'X '.$l)->implode("\n");

        return Http::response([[[$out, $q]]]);
    });

    guideWorker('vi');
    $this->seed(WorkerGuideSeeder::class);

    $res = $this->getJson('/api/v1/guides/emergency')->assertOk();

    expect($res->json('meta.locale'))->toBe('vi');
    expect($res->json('data.title'))->toStartWith('X ');

    // '112' 를 번역기에 넣으면 다른 숫자로 바뀌어 나오는 일이 있다. 번호는 원문 그대로여야 한다.
    $numbers = collect($res->json('data.sections'))
        ->firstWhere('payload.heading', 'X 대한민국 긴급전화');
    expect(collect($numbers['payload']['items'])->pluck('value'))
        ->toContain('112')->toContain('119')->toContain('1345');
});

it('한국어 근로자에게는 번역기를 부르지 않는다', function () {
    Http::fake();

    guideWorker('ko');
    $this->seed(WorkerGuideSeeder::class);

    $this->getJson('/api/v1/guides/pre-training')->assertOk();

    Http::assertNothingSent();
});

it('로그인하지 않으면 안내 자료를 받을 수 없다', function () {
    $this->seed(WorkerGuideSeeder::class);

    $this->getJson('/api/v1/guides')->assertUnauthorized();
    $this->getJson('/api/v1/guides/pre-training')->assertUnauthorized();
});
