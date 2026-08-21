<?php

declare(strict_types=1);

use App\Domains\Support\Models\AccountDeletionRequest;
use App\Http\Controllers\Admin\AccountDeletionAdminController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * 계정 삭제 요청 표 (수요 신청 화면과 같은 [변경 저장] 흐름).
 *
 * 여기서 하는 일은 접수된 요청의 상태를 정하고 메모를 남기는 것뿐이다.
 * 실제 계정 파기는 근로자를 지운 뒤 workers:purge-expired 가 90일 후에 한다(§7-7).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
});

function makeRequest(array $attrs = []): AccountDeletionRequest
{
    return AccountDeletionRequest::create(array_merge([
        'name' => '홍길동',
        'email' => 'gone@example.com',
        'reason' => '더 이상 이용하지 않습니다',
        'status' => AccountDeletionRequest::STATUS_PENDING,
    ], $attrs));
}

function saveDeletions(array $updated = [], array $deleted = []): TestResponse
{
    return actingAs(test()->admin)->postJson(route('admin.account-deletions.save'), [
        'updated' => $updated,
        'deleted' => $deleted,
    ]);
}

it('표에 읽을 수 있는 글자로 내려온다', function () {
    // 표는 코드가 아니라 글자를 그린다 (엑셀로도 그대로 나간다).
    makeRequest();

    $row = AccountDeletionAdminController::rows()[0];

    expect($row['status_label'])->toBe('대기')
        ->and($row['requested_at'])->not->toBeEmpty()
        ->and($row)->toHaveKeys(['name', 'email', 'reason', 'admin_note']);
});

it('상태를 고치면 처리 시각과 처리자가 함께 남는다', function () {
    $req = makeRequest();

    saveDeletions([[
        'current' => [
            'id' => $req->id,
            'status' => AccountDeletionRequest::STATUS_COMPLETED,
            'admin_note' => '계정 비활성 처리함',
        ],
    ]])->assertOk();

    $req->refresh();

    expect($req->status)->toBe(AccountDeletionRequest::STATUS_COMPLETED)
        ->and($req->admin_note)->toBe('계정 비활성 처리함')
        ->and($req->processed_at)->not->toBeNull()
        ->and($req->processed_by)->toBe($this->admin->id);
});

it('대기로 되돌리면 처리 흔적도 지운다', function () {
    // 남겨 두면 '언제 처리했나' 가 거짓이 된다.
    $req = makeRequest([
        'status' => AccountDeletionRequest::STATUS_COMPLETED,
        'processed_at' => now(),
        'processed_by' => $this->admin->id,
    ]);

    saveDeletions([[
        'current' => ['id' => $req->id, 'status' => AccountDeletionRequest::STATUS_PENDING],
    ]])->assertOk();

    $req->refresh();

    expect($req->processed_at)->toBeNull()
        ->and($req->processed_by)->toBeNull();
});

it('처리 시각은 다시 저장해도 처음 것을 지킨다', function () {
    // 메모만 고쳤는데 처리 시각이 오늘로 밀리면 언제 처리했는지 알 수 없다.
    $req = makeRequest([
        'status' => AccountDeletionRequest::STATUS_COMPLETED,
        'processed_at' => now()->subDays(3),
        'processed_by' => $this->admin->id,
    ]);
    $first = $req->processed_at->toDateTimeString();

    saveDeletions([[
        'current' => [
            'id' => $req->id,
            'status' => AccountDeletionRequest::STATUS_COMPLETED,
            'admin_note' => '메모만 고침',
        ],
    ]])->assertOk();

    expect($req->fresh()->processed_at->toDateTimeString())->toBe($first);
});

it('체크한 요청을 지울 수 있고 누가 지웠는지 남는다', function () {
    $a = makeRequest();
    $b = makeRequest(['email' => 'other@example.com']);

    saveDeletions([], [['id' => $a->id]])->assertOk();

    expect(AccountDeletionRequest::find($a->id))->toBeNull()
        ->and(AccountDeletionRequest::find($b->id))->not->toBeNull();

    expect(Activity::where('log_name', 'account-deletion')
        ->where('description', '계정 삭제 요청 삭제')->exists())->toBeTrue();
});

it('쓸 수 없는 상태로는 저장되지 않는다', function () {
    $req = makeRequest();

    saveDeletions([[
        'current' => ['id' => $req->id, 'status' => 'whatever'],
    ]])->assertStatus(422);

    expect($req->fresh()->status)->toBe(AccountDeletionRequest::STATUS_PENDING);
});

it('관리자가 아니면 손댈 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);
    $req = makeRequest();

    actingAs($officer)->postJson(route('admin.account-deletions.save'), [
        'deleted' => [['id' => $req->id]],
    ])->assertForbidden();

    expect(AccountDeletionRequest::find($req->id))->not->toBeNull();
});

it('화면이 표로 그려진다', function () {
    makeRequest();

    $html = actingAs($this->admin)->get(url('admin/screen/account-deletions'))->assertOk()->getContent();

    expect($html)->toContain('grid-account-deletions')
        ->and($html)->toContain('wwConsole(')
        // 접수는 본인이 하는 것이라 여기서 새로 만들지 않는다.
        ->and($html)->toContain('canAdd: false');
});

it('삭제와 수정이 한 번에 들어와도 둘 다 반영된다', function () {
    // [행 삭제] 는 저장하지 않은 다른 변경까지 함께 보낸다 — 한 요청이 한 트랜잭션이라
    // 갈라 보낼 수 없다. 그래서 확인창이 무엇이 함께 저장되는지 먼저 말한다.
    $keep = makeRequest(['email' => 'keep@example.com']);
    $drop = makeRequest(['email' => 'drop@example.com']);

    saveDeletions(
        [['current' => [
            'id' => $keep->id,
            'status' => AccountDeletionRequest::STATUS_COMPLETED,
            'admin_note' => '처리함',
        ]]],
        [['id' => $drop->id]],
    )->assertOk();

    expect(AccountDeletionRequest::find($drop->id))->toBeNull()
        ->and($keep->fresh()->status)->toBe(AccountDeletionRequest::STATUS_COMPLETED)
        ->and($keep->fresh()->admin_note)->toBe('처리함');
});
