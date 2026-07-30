<?php

declare(strict_types=1);

use App\Domains\Site\Models\SitePage;
use App\Domains\Site\Models\SiteSection;
use App\Models\Setting;

/**
 * 회사소개 콘텐츠 API (앱 네이티브 화면).
 *
 * 로그인 전 첫 화면이 이 응답으로 그려진다. 비어 오거나 형태가 달라지면
 * 앱을 처음 연 사람에게 빈 화면이 보인다.
 */
function siteSeedPage(string $key = 'home', array $attributes = []): SitePage
{
    return SitePage::create(array_merge([
        'key' => $key,
        'title' => '모집부터 귀국까지',
        'lead' => '흩어져 있던 행정을 하나로 잇습니다.',
        'hero_image' => 'site/assets/img/hero.jpg',
        'icon' => 'home_rounded',
        'position' => 1,
    ], $attributes));
}

it('로그인 없이 받을 수 있다', function () {
    siteSeedPage();

    $this->getJson('/api/v1/site/pages')
        ->assertOk()
        ->assertJsonPath('data.0.key', 'home');
});

it('이미지 경로를 절대 주소로 바꿔 준다', function () {
    // 앱은 상대 경로를 풀 수 없다. 그대로 내려주면 이미지가 전부 깨진다.
    siteSeedPage();

    $hero = $this->getJson('/api/v1/site/pages')->assertOk()->json('data.0.hero_image');

    expect($hero)->toStartWith('http');
});

it('섹션을 순서대로 내려준다', function () {
    $page = siteSeedPage();
    foreach ([['cta', 3], ['split', 1], ['cards', 2]] as [$type, $position]) {
        SiteSection::create([
            'site_page_id' => $page->id,
            'type' => $type,
            'position' => $position,
            'payload' => ['title' => $type],
        ]);
    }

    $types = collect($this->getJson('/api/v1/site/pages')->json('data.0.sections'))
        ->pluck('type');

    expect($types->all())->toBe(['split', 'cards', 'cta']);
});

it('통계 숫자는 관리자 설정에서 채운다', function () {
    // 시더에는 설정 키만 들어 있다. 값이 바뀌면 앱도 따라 바뀌어야 한다.
    Setting::put('stats.countries', '4');

    $page = siteSeedPage();
    SiteSection::create([
        'site_page_id' => $page->id,
        'type' => 'stats',
        'position' => 1,
        'payload' => ['items' => [['title' => 'stats.countries', 'label' => '송출 협력국']]],
    ]);

    $item = $this->getJson('/api/v1/site/pages')->json('data.0.sections.0.payload.items.0');

    expect($item['value'])->toBe('4')
        ->and($item['label'])->toBe('송출 협력국')
        // 설정 키는 앱에 보낼 이유가 없다
        ->and($item)->not->toHaveKey('title');
});

it('설정값이 비어 있어도 화면이 깨지지 않는다', function () {
    $page = siteSeedPage();
    SiteSection::create([
        'site_page_id' => $page->id,
        'type' => 'stats',
        'position' => 1,
        'payload' => ['items' => [['title' => 'stats.none', 'label' => '없는 값']]],
    ]);

    expect($this->getJson('/api/v1/site/pages')->json('data.0.sections.0.payload.items.0.value'))
        ->not->toBeEmpty();
});

it('앱 메뉴에서 뺀 페이지는 내려가지 않는다', function () {
    siteSeedPage('home');
    siteSeedPage('privacy', ['in_app_nav' => false, 'position' => 9]);

    $keys = collect($this->getJson('/api/v1/site/pages')->json('data'))->pluck('key');

    expect($keys->all())->toBe(['home']);
});

it('페이지를 position 순서로 내려준다', function () {
    siteSeedPage('contact', ['position' => 6]);
    siteSeedPage('home', ['position' => 1]);
    siteSeedPage('about', ['position' => 2]);

    $keys = collect($this->getJson('/api/v1/site/pages')->json('data'))->pluck('key');

    expect($keys->all())->toBe(['home', 'about', 'contact']);
});

it('지원하지 않는 언어는 한국어 원문으로 준다', function () {
    siteSeedPage();

    $this->getJson('/api/v1/site/pages?locale=ja')
        ->assertOk()
        ->assertJsonPath('data.0.title', '모집부터 귀국까지');
});
