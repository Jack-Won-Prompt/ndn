<?php

declare(strict_types=1);

use App\Shared\Translation\SiteTranslator;

/**
 * 회사소개 사이트 언어 전환.
 *
 * 언어 전환은 "고르면 보던 페이지로 돌아온다" 가 전부인데, 돌아갈 곳을 잘못
 * 고르면 무한 리다이렉트가 되어 사이트가 아예 열리지 않는다. 실제로 그런 적이
 * 있어 가드로 남긴다.
 */
it('영어를 포함한 6개 언어를 지원한다', function () {
    expect(SiteTranslator::LOCALES)->toBe(['ko', 'en', 'bn', 'lo', 'si', 'vi', 'ne', 'ky'])
        ->and(SiteTranslator::isSupported('en'))->toBeTrue();
});

it('언어 이름은 그 언어의 문자로 표시된다', function () {
    // 국기가 아니라 언어 이름을 쓰므로, 이름이 비어 있으면 고를 수단이 사라진다.
    foreach (SiteTranslator::LOCALES as $locale) {
        expect(SiteTranslator::NATIVE[$locale] ?? '')->not->toBe('');
    }

    expect(SiteTranslator::NATIVE['en'])->toBe('English');
});

it('언어를 고르면 세션에 저장되고 보던 페이지로 돌아온다', function () {
    $this->get('/')->assertOk();

    $this->get('/set-language/en')
        ->assertRedirect(route('site.home'))
        ->assertSessionHas('site_locale', 'en');
});

it('이전 주소가 언어 전환 경로 자신이어도 무한 리다이렉트가 되지 않는다', function () {
    // 언어 전환 요청도 GET 이라 세션의 '이전 주소' 로 기록된다. 그 상태에서 다시
    // 언어를 고르면 redirect()->back() 이 자기 자신을 가리켜 브라우저가
    // ERR_TOO_MANY_REDIRECTS 를 낸다.
    $this->get('/set-language/en');

    $response = $this->get('/set-language/ko', ['referer' => url('/set-language/en')]);

    $response->assertRedirect(route('site.home'));
    expect($response->headers->get('Location'))->not->toContain('/set-language/');
})->group('guard');

it('지원하지 않는 언어는 무시하고 홈으로 보낸다', function () {
    $this->get('/set-language/ja')
        ->assertRedirect(route('site.home'))
        ->assertSessionMissing('site_locale');
});

it('외부 사이트로는 돌려보내지 않는다', function () {
    // 이전 주소가 남의 도메인이면 그쪽으로 튕겨 보내는 오픈 리다이렉트가 된다.
    $response = $this->get('/set-language/en', ['referer' => 'https://evil.example.com/x']);

    $response->assertRedirect(route('site.home'));
});
