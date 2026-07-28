<?php

declare(strict_types=1);

use App\Models\Setting;

/**
 * 앱 다운로드 링크 — 플레이스토어 미등록 시 홈페이지, 등록 시 플레이스토어로 연결.
 * (관리자 사이트 설정 app.play_store_url 로 제어)
 */
it('플레이스토어 URL 미설정 시 앱 다운로드는 홈페이지로 리다이렉트한다', function () {
    $this->get('/get-app')->assertRedirect(route('site.home'));
});

it('플레이스토어 URL 설정 시 그 주소로 리다이렉트한다', function () {
    $url = 'https://play.google.com/store/apps/details?id=kr.co.ndnkorea.app';
    Setting::put('app.play_store_url', $url);

    $this->get('/get-app')->assertRedirect($url);
});

it('사이트 설정 필드에 플레이스토어 URL 항목이 있다', function () {
    $keys = collect(Setting::fields())->flatMap(fn ($g) => collect($g['fields'])->pluck('key'));
    expect($keys)->toContain('app.play_store_url');
});
