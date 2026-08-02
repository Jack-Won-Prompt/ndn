<?php

declare(strict_types=1);

use App\Domains\Support\Actions\AddServiceRequestReplyAction;
use App\Domains\Support\Actions\ChangeServiceRequestStatusAction;
use App\Domains\Support\Enums\ServiceRequestStatus;
use App\Domains\Support\Models\ServiceRequest;
use App\Domains\Support\Notifications\ServiceRequestCompletedNotification;   // 본문 개인정보 가드는 PersonalDataInNotificationTest 에서 검사
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * SR(Service Request) — 등록 → 담당자 답글 → 상태 관리 → 완료 이메일 (CLAUDE.md §4, §7-3).
 */
beforeEach(fn () => $this->seed(RoleSeeder::class));

function srAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::NdnAdmin->value);

    return $user;
}

it('콘솔에서 SR 을 등록하면 접수 상태로 생성된다', function () {
    $admin = srAdmin();

    $this->actingAs($admin)
        ->postJson(route('admin.service-requests.store'), [
            'title' => '근로자 목록에 지역 필터 추가',
            'body' => '당진시·여주시로 나눠 보고 싶습니다.',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $sr = ServiceRequest::firstOrFail();
    expect($sr->status)->toBe(ServiceRequestStatus::Received);
    expect($sr->requester_user_id)->toBe($admin->id);
    expect($sr->assignee_user_id)->toBeNull();
});

it('제목이나 내용이 없으면 등록되지 않는다', function () {
    $this->actingAs(srAdmin())
        ->postJson(route('admin.service-requests.store'), ['title' => '', 'body' => ''])
        ->assertStatus(422);

    expect(ServiceRequest::count())->toBe(0);
});

it('담당자가 첫 답글을 달면 담당자로 배정되고 처리 중으로 바뀐다', function () {
    $requester = srAdmin();
    $staff = srAdmin();
    $sr = ServiceRequest::factory()->create(['requester_user_id' => $requester->id]);

    app(AddServiceRequestReplyAction::class)->execute($sr, $staff, '확인했습니다. 반영 예정입니다.');

    $sr->refresh();
    expect($sr->assignee_user_id)->toBe($staff->id);
    expect($sr->status)->toBe(ServiceRequestStatus::InProgress);
    expect($sr->replies()->count())->toBe(1);
});

it('등록자 본인이 답글을 달아도 담당자·상태는 바뀌지 않는다', function () {
    $requester = srAdmin();
    $sr = ServiceRequest::factory()->create(['requester_user_id' => $requester->id]);

    app(AddServiceRequestReplyAction::class)->execute($sr, $requester, '내용을 보충합니다.');

    $sr->refresh();
    expect($sr->assignee_user_id)->toBeNull();
    expect($sr->status)->toBe(ServiceRequestStatus::Received);
});

it('적용 완료로 바꾸면 등록자에게 완료 이메일이 발송된다', function () {
    Notification::fake();

    $requester = srAdmin();
    $staff = srAdmin();
    $sr = ServiceRequest::factory()->create(['requester_user_id' => $requester->id]);

    app(ChangeServiceRequestStatusAction::class)
        ->execute($sr, ServiceRequestStatus::Completed, $staff);

    $sr->refresh();
    expect($sr->status)->toBe(ServiceRequestStatus::Completed);
    expect($sr->completed_at)->not->toBeNull();
    expect($sr->completed_by)->toBe($staff->id);

    Notification::assertSentTo(
        $requester,
        ServiceRequestCompletedNotification::class,
        fn ($n) => $n->serviceRequestId === $sr->id,
    );
});

it('이미 완료된 SR 을 다시 완료로 저장해도 이메일이 중복 발송되지 않는다', function () {
    Notification::fake();

    $requester = srAdmin();
    $sr = ServiceRequest::factory()->create([
        'requester_user_id' => $requester->id,
        'status' => ServiceRequestStatus::Completed,
        'completed_at' => now(),
    ]);

    app(ChangeServiceRequestStatusAction::class)
        ->execute($sr, ServiceRequestStatus::Completed, srAdmin());

    Notification::assertNothingSent();
});

it('반려로 종료해도 이메일은 나가지 않는다', function () {
    Notification::fake();

    $sr = ServiceRequest::factory()->create(['requester_user_id' => srAdmin()->id]);

    app(ChangeServiceRequestStatusAction::class)
        ->execute($sr, ServiceRequestStatus::Rejected, srAdmin());

    expect($sr->refresh()->status)->toBe(ServiceRequestStatus::Rejected);
    Notification::assertNothingSent();
});

it('허용되지 않은 상태 전이는 막힌다', function () {
    $sr = ServiceRequest::factory()->create([
        'requester_user_id' => srAdmin()->id,
        'status' => ServiceRequestStatus::Completed,
    ]);

    expect(fn () => app(ChangeServiceRequestStatusAction::class)
        ->execute($sr, ServiceRequestStatus::Received, srAdmin()))
        ->toThrow(RuntimeException::class);
});

it('콘솔 SR 화면이 열리고 등록된 SR 이 보인다', function () {
    ServiceRequest::factory()->create([
        'requester_user_id' => srAdmin()->id,
        'title' => '지역 필터 추가 요청',
    ]);

    $this->actingAs(srAdmin())
        ->get(url('admin/screen/service-requests'))
        ->assertOk()
        ->assertSee('지역 필터 추가 요청')
        ->assertSee('SR · 서비스 요청');
});

it('상세에는 현재 상태에서 갈 수 있는 상태만 내려온다', function () {
    $sr = ServiceRequest::factory()->create(['requester_user_id' => srAdmin()->id]);

    $res = $this->actingAs(srAdmin())
        ->getJson(route('admin.service-requests.show', $sr))
        ->assertOk();

    expect(collect($res->json('transitions'))->pluck('value')->all())
        ->toBe(['in_progress', 'completed', 'rejected']);
});
