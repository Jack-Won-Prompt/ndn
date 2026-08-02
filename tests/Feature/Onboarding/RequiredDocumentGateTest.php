<?php

declare(strict_types=1);

use App\Domains\Onboarding\Models\DocumentConsent;
use App\Domains\Onboarding\Models\RequiredDocument;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RequiredDocumentSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * 필수 확인·동의 게이트 — 미동의 상태로는 앱의 다음 화면으로 넘어가지 못한다
 * (근로자 의무사항·표준근로계약서·상해보험 약정서 등, CLAUDE.md §6·§9).
 */
function docWorker(array $attrs = []): Worker
{
    $worker = Worker::factory()->create($attrs);
    Sanctum::actingAs($worker, ['*']);

    return $worker;
}

it('필수 문서가 없으면 게이트가 통과시킨다', function () {
    docWorker();

    $this->getJson('/api/v1/dashboard')->assertOk();
});

it('미동의 문서가 있으면 다음 화면이 409 로 막히고 남은 문서가 내려온다', function () {
    $worker = docWorker(['locale' => 'ko']);
    // 시더가 만든 5종은 미사용 상태이므로, 게이트 검사는 별도 코드로 만든 문서로 한다.
    RequiredDocument::factory()->create(['code' => 'test_duties']);

    $res = $this->getJson('/api/v1/dashboard')->assertStatus(409);

    expect($res->json('meta.reason'))->toBe('required_documents_pending');
    expect($res->json('meta.pending.0.code'))->toBe('test_duties');
    expect($res->json('message'))->toBe(trans('worker.documents_required', [], 'ko'));
});

it('동의 화면과 로그아웃은 막히지 않는다', function () {
    docWorker();
    RequiredDocument::factory()->create();

    $this->getJson('/api/v1/required-documents')->assertOk();
    $this->getJson('/api/v1/me')->assertOk();
});

it('모두 동의하면 게이트를 통과한다', function () {
    $worker = docWorker();
    $a = RequiredDocument::factory()->create(['code' => 'test_duties']);
    $b = RequiredDocument::factory()->create(['code' => 'test_contract']);

    // 하나만 동의하면 아직 막힌다
    $this->postJson('/api/v1/required-documents/agree', ['document_ids' => [$a->id]])
        ->assertOk()->assertJsonPath('data.all_agreed', false);
    $this->getJson('/api/v1/dashboard')->assertStatus(409);

    $this->postJson('/api/v1/required-documents/agree', ['document_ids' => [$b->id]])
        ->assertOk()->assertJsonPath('data.all_agreed', true);
    $this->getJson('/api/v1/dashboard')->assertOk();

    expect(DocumentConsent::where('worker_id', $worker->id)->count())->toBe(2);
});

it('열람만 하는 문서(동의 불필요)는 게이트를 막지 않는다', function () {
    docWorker();
    RequiredDocument::factory()->optional()->create();

    $this->getJson('/api/v1/dashboard')->assertOk();
});

it('사용하지 않는 문서는 목록과 게이트에서 모두 빠진다', function () {
    docWorker();
    RequiredDocument::factory()->inactive()->create();

    $this->getJson('/api/v1/dashboard')->assertOk();
    $this->getJson('/api/v1/required-documents')->assertOk()->assertJsonCount(0, 'data');
});

it('문안이 바뀌어 버전이 오르면 다시 동의를 받는다', function () {
    docWorker();
    $doc = RequiredDocument::factory()->create();

    $this->postJson('/api/v1/required-documents/agree', ['document_ids' => [$doc->id]])->assertOk();
    $this->getJson('/api/v1/dashboard')->assertOk();

    $doc->increment('version');

    $this->getJson('/api/v1/dashboard')->assertStatus(409);
});

it('같은 문서에 두 번 동의해도 기록은 한 건이다', function () {
    $worker = docWorker();
    $doc = RequiredDocument::factory()->create();

    $this->postJson('/api/v1/required-documents/agree', ['document_ids' => [$doc->id]])->assertOk();
    $this->postJson('/api/v1/required-documents/agree', ['document_ids' => [$doc->id]])->assertOk();

    expect(DocumentConsent::where('worker_id', $worker->id)->count())->toBe(1);
});

it('본문은 근로자 언어로 내려오고 번역이 없으면 한국어로 떨어진다', function () {
    docWorker(['locale' => 'vi']);
    RequiredDocument::factory()->create();

    $res = $this->getJson('/api/v1/required-documents')->assertOk();
    expect($res->json('data.0.title'))->toBe('Nghĩa vụ của người lao động');

    // 라오어 번역이 없는 근로자는 한국어 원문을 본다 (빈 화면보다 낫다)
    docWorker(['locale' => 'lo']);
    $res = $this->getJson('/api/v1/required-documents')->assertOk();
    expect($res->json('data.0.title'))->toBe('근로자 의무사항');
});

it('시더는 요청한 문서 5종을 미사용 상태로 만든다', function () {
    $this->seed(RequiredDocumentSeeder::class);

    expect(RequiredDocument::orderBy('sort_order')->pluck('code')->all())->toBe([
        'worker_duties',
        'standard_contract',
        'insurance_agreement',
        'retention_agreement',
        'deposit_service_agreement',
    ]);
    // 본문이 비어 있으므로 근로자에게 노출되지 않는다
    expect(RequiredDocument::where('active', true)->count())->toBe(0);
});

it('한국어 본문 없이 사용으로 켤 수 없다', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);

    $doc = RequiredDocument::factory()->inactive()->create();

    $this->actingAs($admin)
        ->postJson(route('admin.required-documents.update', $doc), [
            'locales' => ['ko' => ['title' => '근로자 의무사항', 'body' => '']],
            'active' => true,
        ])
        ->assertStatus(422);

    expect($doc->refresh()->active)->toBeFalse();
});

it('새 버전으로 저장하면 버전이 오른다', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);

    $doc = RequiredDocument::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('admin.required-documents.update', $doc), [
            'locales' => ['ko' => ['title' => '근로자 의무사항', 'body' => '바뀐 문안']],
            'active' => true,
            'bump_version' => true,
        ])
        ->assertOk();

    expect($doc->refresh()->version)->toBe(2);
});
