<?php

declare(strict_types=1);

use App\Domains\Onboarding\Models\RequiredDocument;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RequiredDocumentSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * 필수 문서 원본 내려받기 — 파일명은 근로자 언어로 나간다 (CLAUDE.md §6·§9).
 *
 * 근로 동의서 같은 서명 서식은 화면에 옮겨 적지 않고 원본을 받아 읽는다.
 * 옮겨 적으면 법적 문안이 원본과 달라질 수 있다.
 */
function fileWorker(string $locale = 'ko'): Worker
{
    $worker = Worker::factory()->create(['locale' => $locale]);
    Sanctum::actingAs($worker, ['*']);

    return $worker;
}

it('시더가 근로 동의서에 원본 파일을 붙이고 켜 둔다', function () {
    $this->seed(RequiredDocumentSeeder::class);

    $doc = RequiredDocument::where('code', 'work_consent')->firstOrFail();

    expect($doc->file)->toBe('work-consent.pdf');
    expect($doc->hasFile())->toBeTrue();
    // 켜지는 않는다 — 켜는 순간 미동의 근로자 전원이 앱에서 막힌다.
    expect($doc->active)->toBeFalse();
});

it('목록에 내려받기 주소가 함께 나온다', function () {
    fileWorker();
    $doc = RequiredDocument::factory()->create(['file' => 'work-consent.pdf']);

    $res = $this->getJson('/api/v1/required-documents')->assertOk();

    $row = collect($res->json('data'))->firstWhere('id', $doc->id);
    expect($row['download_url'])->toContain('/required-documents/'.$doc->id.'/file');
});

it('파일이 붙지 않은 문서는 내려받기 주소가 없다', function () {
    fileWorker();
    $doc = RequiredDocument::factory()->create(['file' => null]);

    $res = $this->getJson('/api/v1/required-documents')->assertOk();

    expect(collect($res->json('data'))->firstWhere('id', $doc->id)['download_url'])->toBeNull();
});

it('원본을 내려받으면 근로자 언어의 파일명으로 나간다', function () {
    fileWorker('vi');
    $doc = RequiredDocument::factory()->create([
        'file' => 'work-consent.pdf',
        'translations' => [
            'ko' => ['title' => '근로 동의서', 'body' => ''],
            'vi' => ['title' => 'Bản đồng ý lao động', 'body' => ''],
        ],
    ]);

    $res = $this->get('/api/v1/required-documents/'.$doc->id.'/file')->assertOk();

    // 한글·베트남어 파일명이 깨지지 않도록 RFC 5987 로 인코딩되어 나가야 한다.
    $disposition = $res->headers->get('content-disposition');
    expect($disposition)->toContain("filename*=utf-8''");
    expect(rawurldecode($disposition))->toContain('Bản đồng ý lao động.pdf');
});

it('번역이 없는 언어는 한국어 파일명으로 떨어진다', function () {
    fileWorker('lo');
    $doc = RequiredDocument::factory()->create([
        'file' => 'work-consent.pdf',
        'translations' => ['ko' => ['title' => '근로 동의서', 'body' => '']],
    ]);

    $res = $this->get('/api/v1/required-documents/'.$doc->id.'/file')->assertOk();

    expect(rawurldecode($res->headers->get('content-disposition')))->toContain('근로 동의서.pdf');
});

it('사용하지 않는 문서나 파일 없는 문서는 내려받을 수 없다', function () {
    fileWorker();

    $off = RequiredDocument::factory()->inactive()->create(['file' => 'work-consent.pdf']);
    $this->get('/api/v1/required-documents/'.$off->id.'/file')->assertNotFound();

    $noFile = RequiredDocument::factory()->create(['file' => null]);
    $this->get('/api/v1/required-documents/'.$noFile->id.'/file')->assertNotFound();
});

it('관리자도 콘솔에서 원본을 내려받을 수 있다', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);

    $doc = RequiredDocument::factory()->create([
        'file' => 'work-consent.pdf',
        'translations' => ['ko' => ['title' => '근로 동의서', 'body' => '']],
    ]);

    // 목록·상세 응답에 내려받기 주소가 실린다.
    $this->actingAs($admin)
        ->getJson(route('admin.required-documents.show', $doc))
        ->assertOk()
        ->assertJsonPath('file', 'work-consent.pdf');

    $res = $this->actingAs($admin)->get(route('admin.required-documents.file', $doc))->assertOk();
    expect(rawurldecode($res->headers->get('content-disposition')))->toContain('근로 동의서.pdf');
});

it('로그인하지 않으면 원본을 받을 수 없다', function () {
    $doc = RequiredDocument::factory()->create(['file' => 'work-consent.pdf']);

    $this->getJson('/api/v1/required-documents/'.$doc->id.'/file')->assertUnauthorized();
});
