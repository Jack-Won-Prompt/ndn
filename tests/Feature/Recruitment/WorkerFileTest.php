<?php

declare(strict_types=1);

use App\Domains\Recruitment\Enums\WorkerFileType;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use App\Http\Controllers\Admin\WorkerFileController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * 근로자 개인 서류 — 본사가 직접 가입시킬 때 함께 보관하는 여권 사본·건강검진 등.
 *
 * 전원 공통 서식(required_documents)과 다르다. 그쪽은 '모두가 동의하는 약관',
 * 이쪽은 '이 사람의 서류' 다. 지금까지는 개인 서류를 둘 자리가 없었다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake(WorkerFile::DISK);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);

    $this->worker = Worker::factory()->create(['name' => '응우옌']);
});

it('서류를 올리면 근로자별 폴더에 저장된다', function () {
    actingAs($this->admin)
        ->post(route('admin.workers.files.store', $this->worker), [
            'type' => WorkerFileType::Passport->value,
            'file' => UploadedFile::fake()->create('여권 사본.pdf', 200, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $file = WorkerFile::where('worker_id', $this->worker->id)->firstOrFail();

    // 근로자별로 묶어 둔다 — 파기(§7-7) 때 통째로 지우기 쉽다.
    expect($file->path)->toStartWith(WorkerFile::DIR.'/'.$this->worker->id.'/');
    // 저장 이름은 ASCII. 한글 파일명은 서버·백업에서 깨진다.
    expect(basename($file->path))->toMatch('/^passport_[A-Za-z0-9]+\.pdf$/');
    // 화면에는 올린 그대로 보여 준다.
    expect($file->original_name)->toBe('여권 사본.pdf');
    expect($file->uploaded_by)->toBe($this->admin->id);

    Storage::disk(WorkerFile::DISK)->assertExists($file->path);
});

it('서류를 열면 열람 기록이 남는다', function () {
    // 여권 사본은 그 자체로 민감정보다(§7-6).
    actingAs($this->admin)->post(route('admin.workers.files.store', $this->worker), [
        'type' => WorkerFileType::Passport->value,
        'file' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'),
    ])->assertOk();

    $file = WorkerFile::where('worker_id', $this->worker->id)->firstOrFail();

    actingAs($this->admin)->get(route('admin.workers.files.show', [$this->worker, $file]))->assertOk();

    $log = Activity::where('log_name', 'personal-data-access')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->properties['reason'])->toBe('worker-file:passport')
        ->and($log->subject_id)->toBe($this->worker->id);
});

it('다른 근로자의 서류는 열 수 없다', function () {
    $other = Worker::factory()->create();
    $file = WorkerFile::factory()->create(['worker_id' => $other->id]);

    actingAs($this->admin)
        ->get(route('admin.workers.files.show', [$this->worker, $file]))
        ->assertNotFound();
});

it('실행 파일이나 너무 큰 파일은 받지 않는다', function () {
    actingAs($this->admin)
        ->post(route('admin.workers.files.store', $this->worker), [
            'type' => WorkerFileType::Other->value,
            'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])
        ->assertStatus(302);

    actingAs($this->admin)
        ->post(route('admin.workers.files.store', $this->worker), [
            'type' => WorkerFileType::Other->value,
            'file' => UploadedFile::fake()->create('big.pdf', WorkerFile::MAX_KB + 1, 'application/pdf'),
        ])
        ->assertStatus(302);

    expect(WorkerFile::count())->toBe(0);
});

it('유효기간이 지난 서류를 만료로 표시한다', function () {
    // 비자가 지난 줄 모르면 사고가 난다.
    $expired = WorkerFile::factory()->create([
        'worker_id' => $this->worker->id,
        'type' => WorkerFileType::Visa->value,
        'expires_on' => now()->subDay(),
    ]);
    $soon = WorkerFile::factory()->create([
        'worker_id' => $this->worker->id,
        'type' => WorkerFileType::Health->value,
        'expires_on' => now()->addDays(10),
    ]);
    $fine = WorkerFile::factory()->create([
        'worker_id' => $this->worker->id,
        'expires_on' => now()->addYear(),
    ]);

    expect($expired->isExpired())->toBeTrue()
        ->and($soon->isExpired())->toBeFalse()
        ->and($soon->expiresSoon())->toBeTrue()
        ->and($fine->expiresSoon())->toBeFalse();
});

it('파일이 사라진 서류는 목록에서 드러난다', function () {
    // 목록에만 남아 있으면 있는 줄 안다.
    WorkerFile::factory()->create(['worker_id' => $this->worker->id, 'path' => 'worker-files/9/gone.pdf']);

    $rows = WorkerFileController::rows($this->worker);

    expect($rows[0]['missing'])->toBeTrue();
});

it('서류를 지우면 파일도 함께 지워진다', function () {
    // 남겨 두면 파기 요청(§7-7) 때 빠뜨린다.
    actingAs($this->admin)->post(route('admin.workers.files.store', $this->worker), [
        'type' => WorkerFileType::Health->value,
        'file' => UploadedFile::fake()->create('h.pdf', 10, 'application/pdf'),
    ])->assertOk();

    $file = WorkerFile::where('worker_id', $this->worker->id)->firstOrFail();
    $path = $file->path;

    actingAs($this->admin)
        ->deleteJson(route('admin.workers.files.destroy', [$this->worker, $file]))
        ->assertOk();

    expect(WorkerFile::count())->toBe(0);
    Storage::disk(WorkerFile::DISK)->assertMissing($path);
});

it('근로자가 지워지면 서류 기록도 함께 사라진다', function () {
    WorkerFile::factory()->count(3)->create(['worker_id' => $this->worker->id]);

    $this->worker->forceDelete();

    expect(WorkerFile::count())->toBe(0);
});

it('관리자가 아니면 서류를 올릴 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    actingAs($officer)
        ->post(route('admin.workers.files.store', $this->worker), [
            'type' => WorkerFileType::Passport->value,
            'file' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    expect(WorkerFile::count())->toBe(0);
});

it('근로자 상세에 서류 목록과 올리기 주소가 함께 나온다', function () {
    WorkerFile::factory()->create([
        'worker_id' => $this->worker->id,
        'type' => WorkerFileType::Passport->value,
    ]);

    $res = actingAs($this->admin)
        ->getJson(url('admin/screen/workers/'.$this->worker->id.'?format=json'))
        ->assertOk();

    expect($res->json('files.0.type_label'))->toBe('여권 사본')
        ->and($res->json('file_types.passport'))->toBe('여권 사본')
        ->and($res->json('file_upload_url'))->toContain('/files');
});
