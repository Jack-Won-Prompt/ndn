<?php

declare(strict_types=1);

use App\Domains\Demand\Models\City;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use App\Domains\Recruitment\Support\ApplicationDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * 근로자 앱 가입 — 서류를 함께 올린다.
 *
 * 웹(`/apply`)이 먼저 열린 창구다. 앱이 다른 모양으로 보내면 같은 자료가 두
 * 갈래로 쌓이고, 담당자가 콘솔에서 보는 목록이 어느 쪽으로 들어왔느냐에 따라
 * 달라진다. **두 입구가 같은 곳에 같은 모양으로 넣는지**를 본다.
 */
beforeEach(function () {
    Storage::fake(WorkerFile::DISK);

    $this->city = City::factory()->create(['recruiting' => true, 'quota' => null]);
});

function appApplyBody(array $overrides = []): array
{
    return array_merge([
        'name' => 'Nguyen Van Test',
        'email' => 'app.apply@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'nationality' => 'VN',
        'city_id' => test()->city->id,
        'locale' => 'vi',
        'passport_no' => 'A9876543',
    ], $overrides);
}

it('서류 없이도 가입 신청이 접수된다', function () {
    // 현지에서 스캔본을 바로 구하지 못하는 경우가 많다. 막으면 신청이 끊긴다.
    postJson('/api/v1/auth/register', appApplyBody())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.documents', 0);

    expect(WorkerFile::count())->toBe(0);
});

it('여러 파일을 유형 구분 없이 함께 올린다', function () {
    postJson('/api/v1/auth/register', appApplyBody([
        'documents' => [
            UploadedFile::fake()->image('passport.jpg'),
            UploadedFile::fake()->create('범죄경력증명.pdf', 100, 'application/pdf'),
        ],
    ]))->assertCreated()->assertJsonPath('data.documents', 2);

    $worker = Worker::where('email', 'app.apply@example.com')->firstOrFail();
    $files = WorkerFile::where('worker_id', $worker->id)->get();

    expect($files)->toHaveCount(2)
        // 웹과 같다 — 분류는 담당자가 한다.
        ->and($files->pluck('type')->unique()->pluck('value')->all())->toBe(['other'])
        // 화면에는 올린 이름 그대로, 저장은 ASCII 로.
        ->and($files->pluck('original_name')->all())->toContain('범죄경력증명.pdf')
        ->and(basename($files[1]->path))->toMatch('/^apply_[A-Za-z0-9]+\.pdf$/')
        // 본인이 올린 것과 담당자가 올린 것을 구분한다.
        ->and($files->pluck('uploaded_by')->unique()->all())->toBe([null]);

    Storage::disk(WorkerFile::DISK)->assertExists($files[0]->path);
});

it('실행 파일은 받지 않는다', function () {
    postJson('/api/v1/auth/register', appApplyBody([
        'documents' => [UploadedFile::fake()->create('payload.php', 10, 'application/x-php')],
    ]))->assertStatus(422)->assertJsonValidationErrors('documents.0');

    // 계정도 만들어지면 안 된다 — 검증은 Action 앞에서 끝난다.
    expect(Worker::where('email', 'app.apply@example.com')->exists())->toBeFalse();
});

it('한 번에 올릴 수 있는 개수를 넘기면 거절한다', function () {
    $files = [];
    for ($i = 0; $i <= ApplicationDocuments::MAX_FILES; $i++) {
        $files[] = UploadedFile::fake()->image("p{$i}.jpg");
    }

    postJson('/api/v1/auth/register', appApplyBody(['documents' => $files]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('documents');

    expect(WorkerFile::count())->toBe(0);
});

it('가입 화면이 물어볼 서류를 근로자 언어로 알려 준다', function () {
    // 앱에 목록을 박아 두면 받는 서류가 바뀔 때 스토어 심사를 기다려야 하고,
    // 그 사이 웹과 앱이 서로 다른 서류를 안내한다.
    $response = getJson('/api/v1/signup/documents?locale=vi')
        ->assertOk()
        ->assertJsonPath('meta.max_files', ApplicationDocuments::MAX_FILES)
        // 서류가 없어도 접수된다는 사실을 앱이 화면에서 분명히 하도록.
        ->assertJsonPath('meta.required', false)
        ->assertJsonPath('meta.locale', 'vi');

    expect($response->json('data.expected'))
        ->toHaveCount(4)
        ->toContain('Bản sao hộ chiếu')
        ->and($response->json('data.hint'))->not->toBe('');
});

it('모르는 언어는 한국어로 떨어뜨린다', function () {
    getJson('/api/v1/signup/documents?locale=th')
        ->assertOk()
        ->assertJsonPath('meta.locale', 'ko')
        ->assertJsonPath('data.expected.0', '여권 사본');
});
