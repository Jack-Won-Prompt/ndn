<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Support\Facades\View;

/**
 * 설정을 바꾼 뒤 뷰에 반영한다.
 *
 * AppServiceProvider 가 부팅 때 View::share('S', Setting::allKeyed()) 로 값을
 * 한 번 잡는다. 운영은 요청마다 부팅되니 문제가 없지만, 테스트는 부팅이 한 번뿐이라
 * 이후에 바꾼 설정이 뷰까지 가지 않는다.
 */
function reshareSettings(): void
{
    View::share('S', Setting::allKeyed());
}

/**
 * 홈 히어로 배경 사진 — 설정값으로 켜고 끈다.
 *
 * 운영에서 사진이 안 나온 적이 있다. 파일은 배포됐지만 설정값이 로컬에만 있었다.
 * 마이그레이션이 기본값을 채우는지, 관리자가 바꾸거나 끌 수 있는지 함께 검사한다.
 */
it('마이그레이션이 기본 히어로 사진을 채운다', function () {
    // RefreshDatabase 가 마이그레이션을 돌린 뒤이므로 값이 이미 있어야 한다.
    expect(Setting::get('site.hero_image'))->toBe('harvest.jpg');
});

it('설정한 사진이 홈 히어로에 깔린다', function () {
    Setting::put('site.hero_image', 'harvest.jpg');
    reshareSettings();

    $this->get('/')
        ->assertOk()
        ->assertSee('nd-hero--photo', false)
        ->assertSee('site/assets/img/harvest.jpg', false);
});

it('설정을 비우면 사진 없이 렌더된다', function () {
    Setting::put('site.hero_image', '');
    reshareSettings();

    $this->get('/')
        ->assertOk()
        ->assertDontSee('nd-hero--photo', false)
        ->assertDontSee('nd-hero__bg', false);
});

it('히어로 사진 파일이 저장소에 있다', function () {
    // 설정값만 있고 파일이 없으면 화면에 깨진 이미지가 뜬다.
    expect(is_file(public_path('site/assets/img/'.Setting::get('site.hero_image'))))->toBeTrue();
});
