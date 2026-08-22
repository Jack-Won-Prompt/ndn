<?php

declare(strict_types=1);

use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Onboarding\Models\RequiredDocument;
use App\Domains\Support\Enums\ServiceRequestStatus;
use App\Domains\Support\Models\ServiceRequest;
use App\Http\Controllers\Admin\FarmVisitController;
use App\Http\Controllers\Admin\RequiredDocumentAdminController;
use App\Http\Controllers\Admin\ServiceRequestController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;

/**
 * 콘솔의 목록 화면은 모두 wwGrid 로 그린다.
 *
 * 표가 그리는 것은 글자다 — 참/거짓이나 배열을 그대로 두면 화면에 'true' 나
 * '[object Object]' 가 뜨고, 엑셀 다운로드에도 그대로 따라간다. 그래서 각 화면의
 * rows() 가 읽을 수 있는 칸을 함께 내려주는지 확인한다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
});

it('필수 동의 문서 — 번역이 몇 개 찼는지 한눈에 보인다', function () {
    // 이 화면의 핵심은 '어느 언어가 아직 비었나' 다(§6). 채운 것만 늘어놓으면
    // 무엇이 빠졌는지 세어 봐야 한다.
    $doc = RequiredDocument::query()->first();

    if ($doc === null) {
        $this->markTestSkipped('시더에 필수 문서가 없습니다.');
    }

    $row = collect(RequiredDocumentAdminController::rows())->firstWhere('id', $doc->id);

    expect($row['locales_label'])->toContain('/'.count(RequiredDocument::LOCALES))
        ->and($row['version_label'])->toStartWith('v')
        ->and($row['required_label'])->toBeIn(['필수', '열람만'])
        ->and($row['active_label'])->toBeIn(['사용', '미사용'])
        ->and($row['edit'])->toBe('본문 편집 ▸');
});

it('필수 동의 문서 화면이 표로 그려진다', function () {
    $html = actingAs($this->admin)->get(url('admin/screen/required-documents'))->assertOk()->getContent();

    expect($html)->toContain('grid-required-documents')
        ->and($html)->toContain('wwConsole(');
});

it('농가 방문 점검 — 사진·애로가 글자로 온다', function () {
    $farm = Farm::factory()->create(['name' => '점검농가']);
    $visit = FarmVisit::factory()->create([
        'farm_id' => $farm->id,
        'worker_headcount' => 3,
        'issue_note' => '숙소 난방 고장',
    ]);

    $row = collect(FarmVisitController::rows())->firstWhere('id', $visit->id);

    expect($row['farm'])->toBe('점검농가')
        ->and($row['headcount_label'])->toBe('3명')
        ->and($row['issue_label'])->toBe('있음')
        // 사진이 없으면 빈 칸이다 — '0장' 은 눈만 어지럽힌다.
        ->and($row['photos_label'])->toBe('')
        ->and($row['detail'])->toBe('상세 ▸');
});

it('농가 방문 점검 화면이 표로 그려진다', function () {
    Farm::factory()->create(['name' => '표에보일농가']);

    $html = actingAs($this->admin)->get(url('admin/screen/farmvisits'))->assertOk()->getContent();

    expect($html)->toContain('grid-farmvisits')
        ->and($html)->toContain('wwConsole(');
});

it('SR — 번호가 # 붙은 글자로 온다', function () {
    $sr = ServiceRequest::create([
        'title' => '화면 오류 신고',
        'body' => '내용',
        'requester_user_id' => $this->admin->id,
        'status' => ServiceRequestStatus::Received,
    ]);

    $row = collect(ServiceRequestController::rows())->firstWhere('id', $sr->id);

    expect($row['sr_no'])->toBe('#'.$sr->id)
        ->and($row['detail'])->toBe('상세 ▸')
        ->and($row['status_label'])->not->toBeEmpty();
});

it('SR 화면이 표로 그려진다', function () {
    ServiceRequest::create([
        'title' => '표에 보일 SR',
        'body' => '내용',
        'requester_user_id' => $this->admin->id,
        'status' => ServiceRequestStatus::Received,
    ]);

    $html = actingAs($this->admin)->get(url('admin/screen/service-requests'))->assertOk()->getContent();

    expect($html)->toContain('grid-service-requests')
        ->and($html)->toContain('표에 보일 SR');
});

it('콘솔의 목록 화면에 손으로 그린 표가 남아 있지 않다', function () {
    // 다음에 화면을 하나 더 만들 때 옛 방식으로 돌아가지 않도록 못 박는다.
    // (매칭 화면의 상세 패널 안 작은 표는 그리드가 아니라 목록이 아니므로 뺀다.)
    $screens = glob(resource_path('views/admin/screens/*.blade.php'));
    $withTable = [];

    foreach ($screens as $path) {
        $name = basename($path, '.blade.php');

        // matching 은 상세 패널 안 작은 표(목록이 아니다),
        // life-checklist·work-reviews 는 아직 옮기지 않은 화면이다.
        $skip = ['layout', 'matching', 'worker_detail', 'life-checklist', 'work-reviews'];

        if (in_array($name, $skip, true) || str_starts_with($name, '_')) {
            continue;
        }

        if (preg_match('~<table~', (string) file_get_contents($path))) {
            $withTable[] = $name;
        }
    }

    expect($withTable)->toBe([]);
});
