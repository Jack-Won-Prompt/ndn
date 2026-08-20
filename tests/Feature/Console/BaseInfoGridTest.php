<?php

declare(strict_types=1);

use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

/**
 * 농가·지자체 기준정보 그리드.
 *
 * 여기서 등록한 농가가 수요 신청·매칭·점검표의 기준이 된다. 값이 조용히 어긋나면
 * 그 뒤 모든 화면이 함께 어긋나므로, 저장이 실제로 무엇을 남기는지 확인한다.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
});

/** 그리드가 보내는 모양 그대로 저장 요청을 만든다. */
function saveFarms(array $added = [], array $updated = []): TestResponse
{
    return actingAs(test()->admin)->postJson(route('admin.grid.farms.save'), [
        'added' => $added,
        'updated' => $updated,
        'deleted' => [],
    ]);
}

it('경영체등록번호를 저장하고 목록에 돌려준다', function () {
    $city = City::factory()->create();

    saveFarms([[
        'name' => '햇살농원',
        'city_id' => $city->id,
        'address' => '충청남도 당진시 합덕읍',
        'business_reg_no' => '0123456789',
    ]])->assertOk();

    $farm = Farm::firstOrFail();

    // 앞자리 0 이 살아 있어야 한다 — 숫자로 다루면 123456789 가 된다.
    expect($farm->business_reg_no)->toBe('0123456789');

    // 저장 뒤 화면이 다시 그려질 때 쓰는 목록에도 있어야 한다.
    $rows = actingAs($this->admin)->postJson(route('admin.grid.farms.save'), [])
        ->assertOk()->json('rows');

    expect($rows[0]['business_reg_no'])->toBe('0123456789');
});

it('번호 사이 공백은 지운다', function () {
    // 같은 번호가 '1234567890' 과 '123 456 7890' 으로 갈라지면 대조할 수 없다.
    saveFarms([['name' => '푸른들농장', 'business_reg_no' => ' 123 456 7890 ']])->assertOk();

    expect(Farm::firstOrFail()->business_reg_no)->toBe('1234567890');
});

it('하이픈은 그대로 둔다', function () {
    // 발급 서류에 하이픈이 찍혀 오는 경우가 있어 적힌 대로 받는다.
    saveFarms([['name' => '드림팜', 'business_reg_no' => '123-45-67890']])->assertOk();

    expect(Farm::firstOrFail()->business_reg_no)->toBe('123-45-67890');
});

it('숫자·하이픈이 아니면 막고 이유를 알려 준다', function () {
    saveFarms([['name' => '옥토농장', 'business_reg_no' => '경영체 1234']])
        ->assertStatus(422)
        ->assertJsonPath('ok', false);

    // 한 행이라도 막히면 트랜잭션이 통째로 돌아간다 — 반쯤 저장된 상태가 남으면 안 된다.
    expect(Farm::count())->toBe(0);
});

it('비워 두어도 저장된다', function () {
    // 이미 등록된 농가는 번호를 모른다. 필수로 걸면 손대는 순간 저장이 막힌다.
    saveFarms([['name' => '토담농장']])->assertOk();

    expect(Farm::firstOrFail()->business_reg_no)->toBeNull();
});

it('기존 농가의 번호를 나중에 채울 수 있다', function () {
    $farm = Farm::factory()->create(['business_reg_no' => null]);

    saveFarms([], [[
        'current' => ['id' => $farm->id, 'name' => $farm->name, 'business_reg_no' => '9876543210'],
    ]])->assertOk();

    expect($farm->fresh()->business_reg_no)->toBe('9876543210');
});

it('엑셀 머리글이 어떻게 적혀 있어도 같은 칸으로 읽는다', function () {
    // 지자체마다 서식이 달라 '경영체등록번호' / '농업경영체 등록번호' 가 섞여 온다.
    $csv = "농가명,주소,농업경영체 등록번호\n햇살농원,당진시 합덕읍,0123456789\n";
    $file = UploadedFile::fake()->createWithContent('farms.csv', $csv);

    $rows = actingAs($this->admin)
        ->post(route('admin.grid.farms.import'), ['file' => $file])
        ->assertOk()->json('rows');

    expect($rows[0]['business_reg_no'])->toBe('0123456789')
        ->and($rows[0]['name'])->toBe('햇살농원');
});

it('관리자가 아니면 기준정보를 바꿀 수 없다', function () {
    $officer = User::factory()->create();
    $officer->assignRole(UserRole::CityOfficer->value);

    actingAs($officer)->postJson(route('admin.grid.farms.save'), [
        'added' => [['name' => '몰래농장']],
    ])->assertForbidden();

    expect(Farm::count())->toBe(0);
});

it('화면에 경영체등록번호 칸이 주소 다음에 있다', function () {
    $html = actingAs($this->admin)->get(url('admin/screen/baseinfo'))->assertOk()->getContent();

    expect($html)->toContain("name: 'business_reg_no'")
        // 순서가 뒤집히면 담당자가 늘 보던 자리에서 못 찾는다.
        ->and(strpos($html, "name: 'business_reg_no'"))
        ->toBeGreaterThan(strpos($html, "name: 'address'"));
});
