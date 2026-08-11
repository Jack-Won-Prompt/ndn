<?php

declare(strict_types=1);

use App\Domains\Onboarding\Models\RequiredDocument;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RequiredDocumentSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;

/**
 * 필수 동의 문서 — 원본 서식 올리기.
 *
 * 법적 서식은 화면에 옮겨 적지 않고 원본을 그대로 받게 한다. 옮겨 적으면 문안이
 * 원본과 달라지고 그건 법적 문서에서 사고가 된다.
 *
 * 이 기능이 없으면 운영은 본문(약관 전문)을 타이핑해야만 문서를 켤 수 있었다 —
 * 시더만 파일을 붙일 수 있었기 때문이다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake(RequiredDocument::DISK);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
});

it('원본을 올리면 문서에 붙고 내려받을 수 있다', function () {
    $doc = RequiredDocument::factory()->inactive()->create(['file' => null, 'code' => 'upload_probe']);

    actingAs($this->admin)
        ->post(route('admin.required-documents.file.upload', $doc), [
            'file' => UploadedFile::fake()->create('표준근로계약서.pdf', 120, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $doc->refresh();

    // 저장 이름은 ASCII 다 — 한글 파일명은 서버·백업에서 깨진다.
    expect($doc->file)->toStartWith('upload_probe_')
        ->and($doc->file)->toEndWith('.pdf')
        ->and($doc->file)->toMatch('/^[\x20-\x7E]+$/');

    Storage::disk(RequiredDocument::DISK)->assertExists($doc->file);

    actingAs($this->admin)->get(route('admin.required-documents.file', $doc))->assertOk();
});

it('원본을 붙이면 본문이 비어도 문서를 켤 수 있다', function () {
    // 이게 이 기능의 핵심이다. 예전에는 본문을 타이핑해야만 켤 수 있었다.
    $doc = RequiredDocument::factory()->inactive()->create([
        'file' => null,
        'translations' => ['ko' => ['title' => '표준근로계약서', 'body' => '']],
    ]);

    // 파일이 없으면 켜지지 않는다.
    actingAs($this->admin)
        ->postJson(route('admin.required-documents.update', $doc), [
            'locales' => ['ko' => ['title' => '표준근로계약서', 'body' => '']],
            'required' => true, 'active' => true,
        ])
        ->assertStatus(422);

    actingAs($this->admin)->post(route('admin.required-documents.file.upload', $doc), [
        'file' => UploadedFile::fake()->create('form.pdf', 50, 'application/pdf'),
    ])->assertOk();

    actingAs($this->admin)
        ->postJson(route('admin.required-documents.update', $doc), [
            'locales' => ['ko' => ['title' => '표준근로계약서', 'body' => '']],
            'required' => true, 'active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($doc->refresh()->active)->toBeTrue();
});

it('문서·이미지가 아닌 파일은 받지 않는다', function () {
    $doc = RequiredDocument::factory()->create(['file' => null]);

    actingAs($this->admin)
        ->post(route('admin.required-documents.file.upload', $doc), [
            'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])
        ->assertStatus(302);   // 폼 검증 실패

    expect($doc->refresh()->file)->toBeNull();
});

it('파일을 바꿔도 예전 파일은 지우지 않는다', function () {
    // 이미 그 서식에 동의한 근로자가 있으면 무엇에 동의했는지 남아 있어야 한다.
    $doc = RequiredDocument::factory()->create(['file' => null]);

    actingAs($this->admin)->post(route('admin.required-documents.file.upload', $doc), [
        'file' => UploadedFile::fake()->create('v1.pdf', 10, 'application/pdf'),
    ])->assertOk();
    $first = $doc->refresh()->file;

    actingAs($this->admin)->post(route('admin.required-documents.file.upload', $doc), [
        'file' => UploadedFile::fake()->create('v2.pdf', 10, 'application/pdf'),
    ])->assertOk();
    $second = $doc->refresh()->file;

    expect($first)->not->toBe($second);
    Storage::disk(RequiredDocument::DISK)->assertExists($first);
    Storage::disk(RequiredDocument::DISK)->assertExists($second);
});

it('켜져 있고 본문도 없는 문서에서는 원본을 뗄 수 없다', function () {
    // 떼면 근로자가 빈 화면에 동의하게 된다.
    $doc = RequiredDocument::factory()->create([
        'file' => 'work-consent.pdf',
        'active' => true,
        'translations' => ['ko' => ['title' => '근로 동의서', 'body' => '']],
    ]);

    actingAs($this->admin)
        ->deleteJson(route('admin.required-documents.file.remove', $doc))
        ->assertStatus(422);

    expect($doc->refresh()->file)->not->toBeNull();

    // 사용을 끄면 뗄 수 있다.
    $doc->update(['active' => false]);
    actingAs($this->admin)
        ->deleteJson(route('admin.required-documents.file.remove', $doc))
        ->assertOk();

    expect($doc->refresh()->file)->toBeNull();
});

it('근로자는 올린 원본을 자기 언어 파일명으로 받는다', function () {
    $doc = RequiredDocument::factory()->create([
        'file' => null,
        'translations' => [
            'ko' => ['title' => '표준근로계약서', 'body' => ''],
            'vi' => ['title' => 'Hợp đồng lao động', 'body' => ''],
        ],
    ]);

    actingAs($this->admin)->post(route('admin.required-documents.file.upload', $doc), [
        'file' => UploadedFile::fake()->create('form.pdf', 10, 'application/pdf'),
    ])->assertOk();

    Sanctum::actingAs(Worker::factory()->create(['locale' => 'vi']), ['*']);

    $res = $this->get('/api/v1/required-documents/'.$doc->id.'/file')->assertOk();

    expect(rawurldecode($res->headers->get('content-disposition')))
        ->toContain('Hợp đồng lao động.pdf');
});

it('관리자가 아니면 원본을 올릴 수 없다', function () {
    $doc = RequiredDocument::factory()->create(['file' => null]);

    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    actingAs($officer)
        ->post(route('admin.required-documents.file.upload', $doc), [
            'file' => UploadedFile::fake()->create('form.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    expect($doc->refresh()->file)->toBeNull();
});

it('시더 재실행 마이그레이션이 근로 동의서를 되살린다', function () {
    // 시더에는 있는데 DB 에는 없는 환경이 있었다. 그 환경은 손대지 않으면 영영 빈다.
    RequiredDocument::query()->delete();
    $this->seed(RequiredDocumentSeeder::class);

    $doc = RequiredDocument::where('code', 'work_consent')->first();

    expect($doc)->not->toBeNull()
        ->and($doc->file)->toBe('work-consent.pdf')
        // 켜지 않는다 — 켜는 순간 미동의 근로자 전원이 앱에서 막힌다.
        ->and($doc->active)->toBeFalse();
});
