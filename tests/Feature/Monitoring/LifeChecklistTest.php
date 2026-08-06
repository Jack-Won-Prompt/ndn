<?php

declare(strict_types=1);

use App\Domains\Monitoring\Models\LifeChecklistCheck;
use App\Domains\Monitoring\Models\LifeChecklistItem;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Admin\LifeChecklistController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\LifeChecklistSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * 한국 생활 체크리스트 (입국 후 1주일 이내 확인사항).
 *
 * 근로자가 앱에서 직접 체크한다. 관리자는 아직 확인하지 않은 사람을 본다.
 * 월별 자가 평가 6항목을 대신하는 화면이다.
 */
function checklistWorker(string $locale = 'ko'): Worker
{
    $worker = Worker::factory()->create(['locale' => $locale, 'status' => WorkerStatus::Active->value]);
    Sanctum::actingAs($worker, ['*']);

    return $worker;
}

it('시더가 확인사항 12항목을 만든다', function () {
    $this->seed(LifeChecklistSeeder::class);

    expect(LifeChecklistItem::count())->toBe(12);
    expect(LifeChecklistItem::query()->active()->first()->code)->toBe('documents_location');
});

it('시더를 다시 돌려도 항목이 늘지 않고 내려 둔 항목이 켜지지 않는다', function () {
    $this->seed(LifeChecklistSeeder::class);
    LifeChecklistItem::where('code', 'living_costs')->update(['active' => false]);

    $this->seed(LifeChecklistSeeder::class);

    expect(LifeChecklistItem::count())->toBe(12);
    expect(LifeChecklistItem::where('code', 'living_costs')->first()->active)->toBeFalse();
});

it('목록에 항목과 본인 체크 여부가 함께 나온다', function () {
    $worker = checklistWorker();
    $this->seed(LifeChecklistSeeder::class);

    $item = LifeChecklistItem::query()->active()->first();
    LifeChecklistCheck::factory()->create([
        'worker_id' => $worker->id,
        'life_checklist_item_id' => $item->id,
    ]);

    $res = $this->getJson('/api/v1/life-checklist')->assertOk();

    expect($res->json('meta.total'))->toBe(12);
    expect($res->json('meta.checked_count'))->toBe(1);
    expect(collect($res->json('data'))->firstWhere('id', $item->id)['checked'])->toBeTrue();
});

it('체크한 항목 전체를 보내면 그대로 맞춰진다', function () {
    $worker = checklistWorker();
    $this->seed(LifeChecklistSeeder::class);

    $ids = LifeChecklistItem::query()->active()->take(3)->pluck('id')->all();

    $this->postJson('/api/v1/life-checklist', ['checked' => $ids])
        ->assertOk()
        ->assertJsonPath('meta.checked_count', 3);

    // 두 개만 남기면 나머지 하나는 지워진다 — 부분 갱신이 아니라 통째로 맞춘다.
    $this->postJson('/api/v1/life-checklist', ['checked' => array_slice($ids, 0, 2)])
        ->assertOk()
        ->assertJsonPath('meta.checked_count', 2);

    expect(LifeChecklistCheck::where('worker_id', $worker->id)->count())->toBe(2);
});

it('같은 요청을 두 번 보내도 결과가 같다', function () {
    $worker = checklistWorker();
    $this->seed(LifeChecklistSeeder::class);

    $ids = LifeChecklistItem::query()->active()->take(4)->pluck('id')->all();

    $this->postJson('/api/v1/life-checklist', ['checked' => $ids])->assertOk();
    $first = LifeChecklistCheck::where('worker_id', $worker->id)->pluck('checked_at')->sort()->values();

    $this->postJson('/api/v1/life-checklist', ['checked' => $ids])->assertOk();

    expect(LifeChecklistCheck::where('worker_id', $worker->id)->count())->toBe(4);
    // 이미 체크된 항목은 확인 시각이 새로 찍히지 않는다.
    expect(LifeChecklistCheck::where('worker_id', $worker->id)->pluck('checked_at')->sort()->values()->all())
        ->toEqual($first->all());
});

it('전부 체크하면 완료로 표시된다', function () {
    checklistWorker();
    $this->seed(LifeChecklistSeeder::class);

    $ids = LifeChecklistItem::query()->active()->pluck('id')->all();

    $this->postJson('/api/v1/life-checklist', ['checked' => $ids])
        ->assertOk()
        ->assertJsonPath('meta.completed', true);
});

it('전부 해제하는 것도 정상 요청이다', function () {
    $worker = checklistWorker();
    $this->seed(LifeChecklistSeeder::class);
    $ids = LifeChecklistItem::query()->active()->take(2)->pluck('id')->all();
    $this->postJson('/api/v1/life-checklist', ['checked' => $ids])->assertOk();

    $this->postJson('/api/v1/life-checklist', ['checked' => []])
        ->assertOk()
        ->assertJsonPath('meta.checked_count', 0);

    expect(LifeChecklistCheck::where('worker_id', $worker->id)->count())->toBe(0);
});

it('꺼 둔 항목은 목록에서 빠지고 체크해도 저장되지 않는다', function () {
    $worker = checklistWorker();
    $this->seed(LifeChecklistSeeder::class);

    $off = LifeChecklistItem::where('code', 'living_costs')->first();
    $off->update(['active' => false]);

    $res = $this->getJson('/api/v1/life-checklist')->assertOk();
    expect($res->json('meta.total'))->toBe(11);
    expect(collect($res->json('data'))->pluck('id'))->not->toContain($off->id);

    $this->postJson('/api/v1/life-checklist', ['checked' => [$off->id]])->assertOk();
    expect(LifeChecklistCheck::where('worker_id', $worker->id)->count())->toBe(0);
});

it('다른 근로자의 체크는 섞이지 않는다', function () {
    $other = Worker::factory()->create();
    $this->seed(LifeChecklistSeeder::class);
    $item = LifeChecklistItem::query()->active()->first();
    LifeChecklistCheck::factory()->create([
        'worker_id' => $other->id,
        'life_checklist_item_id' => $item->id,
    ]);

    checklistWorker();

    $this->getJson('/api/v1/life-checklist')
        ->assertOk()
        ->assertJsonPath('meta.checked_count', 0);
});

it('문구는 근로자 언어로 번역해 나간다', function () {
    Http::fake(function ($request) {
        $q = (string) ($request->data()['q'] ?? '');
        $out = collect(explode("\n", $q))->map(fn ($l) => 'X '.$l)->implode("\n");

        return Http::response([[[$out, $q]]]);
    });

    checklistWorker('bn');
    $this->seed(LifeChecklistSeeder::class);

    $res = $this->getJson('/api/v1/life-checklist')->assertOk();

    expect($res->json('meta.locale'))->toBe('bn');
    expect($res->json('data.0.label'))->toStartWith('X ');
});

it('로그인하지 않으면 체크리스트를 받을 수 없다', function () {
    $this->getJson('/api/v1/life-checklist')->assertUnauthorized();
    $this->postJson('/api/v1/life-checklist', ['checked' => []])->assertUnauthorized();
});

it('콘솔 현황은 덜 된 근로자를 위에 놓는다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(LifeChecklistSeeder::class);

    $done = Worker::factory()->create(['name' => '가근로', 'status' => WorkerStatus::Active->value]);
    $none = Worker::factory()->create(['name' => '나근로', 'status' => WorkerStatus::Active->value]);

    foreach (LifeChecklistItem::query()->active()->pluck('id') as $id) {
        LifeChecklistCheck::factory()->create(['worker_id' => $done->id, 'life_checklist_item_id' => $id]);
    }

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);

    $res = $this->actingAs($admin)->get('/admin/screen/life-checklist')->assertOk();

    $rows = collect(LifeChecklistController::rows());
    expect($rows->first()['worker'])->toBe($none->name);
    expect($rows->first()['state'])->toBe('미시작');
    expect($rows->firstWhere('worker_id', $done->id)['state'])->toBe('완료');
    expect($rows->firstWhere('worker_id', $done->id)['pending'])->toBe([]);

    $res->assertSee('생활 체크리스트');
});

it('관리자는 항목 문구를 고칠 수 있다', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(LifeChecklistSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);

    $item = LifeChecklistItem::where('code', 'ndn_contact')->firstOrFail();

    $this->actingAs($admin)
        ->postJson(route('admin.life-checklist.item.update', $item), [
            'label' => 'NDN KOREA 담당자 연락처를 휴대전화에 저장',
            'hint' => null,
            'active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($item->refresh()->label)->toBe('NDN KOREA 담당자 연락처를 휴대전화에 저장');
});
