<?php

declare(strict_types=1);

use App\Domains\Recruitment\Models\Worker;
use Laravel\Sanctum\Sanctum;

/**
 * 앱 버전 확인 (사이드로딩 배포).
 *
 * 스토어 자동 업데이트가 없으므로 앱이 직접 물어본다. 이 엔드포인트가 막히면
 * 구버전이 영원히 남으므로 인증 없이 열려 있어야 한다.
 */
beforeEach(function () {
    config()->set('mobile.android', [
        'latest_version' => 5,
        'latest_version_name' => '1.2.0',
        'min_version' => 3,
        'download_url' => 'https://ndnkorea.co.kr/app/universal.apk',
        'download_urls' => [
            'arm64-v8a' => 'https://ndnkorea.co.kr/app/arm64.apk',
            'armeabi-v7a' => 'https://ndnkorea.co.kr/app/arm32.apk',
        ],
        'install_page_url' => 'https://ndnkorea.co.kr/app/',
        'release_notes' => ['정착 서비스 신청 추가', '현장 점검 사진'],
    ]);
});

it('인증 없이 접근할 수 있다 (강제 업데이트 대상은 로그인이 안 될 수 있다)', function () {
    $this->getJson('/api/v1/app/version')
        ->assertOk()
        ->assertJsonPath('latest_version', 5)
        ->assertJsonPath('latest_version_name', '1.2.0');
});

it('최신 버전이면 업데이트를 안내하지 않는다', function () {
    $this->getJson('/api/v1/app/version?version_code=5')
        ->assertOk()
        ->assertJsonPath('update_available', false)
        ->assertJsonPath('update_required', false);
});

it('최신보다 낮지만 최소 버전 이상이면 선택 업데이트다', function () {
    $this->getJson('/api/v1/app/version?version_code=4')
        ->assertOk()
        ->assertJsonPath('update_available', true)
        ->assertJsonPath('update_required', false);
});

it('최소 버전 미만이면 강제 업데이트다', function () {
    $this->getJson('/api/v1/app/version?version_code=2')
        ->assertOk()
        ->assertJsonPath('update_available', true)
        ->assertJsonPath('update_required', true);
});

it('기기 ABI 에 맞는 APK 주소를 준다', function (string $abi, string $expected) {
    $this->getJson("/api/v1/app/version?version_code=1&abi={$abi}")
        ->assertOk()
        ->assertJsonPath('download_url', $expected);
})->with([
    ['arm64-v8a', 'https://ndnkorea.co.kr/app/arm64.apk'],
    ['armeabi-v7a', 'https://ndnkorea.co.kr/app/arm32.apk'],
]);

it('모르는 ABI 에는 universal APK 를 준다', function () {
    $this->getJson('/api/v1/app/version?version_code=1&abi=x86_64')
        ->assertOk()
        ->assertJsonPath('download_url', 'https://ndnkorea.co.kr/app/universal.apk');
});

it('버전을 알려주지 않으면 강제하지 않는다 (외부 호출 보호)', function () {
    $this->getJson('/api/v1/app/version')
        ->assertOk()
        ->assertJsonPath('update_required', false);
});

it('변경사항을 줄 단위로 내려준다', function () {
    $this->getJson('/api/v1/app/version?version_code=1')
        ->assertOk()
        ->assertJsonCount(2, 'release_notes');
});

it('응답에 개인정보가 없다 (§7)', function () {
    $body = $this->getJson('/api/v1/app/version?version_code=1')->getContent();

    // 버전·주소만 담긴다. 사용자 식별 정보가 섞이면 안 된다.
    expect($body)->not->toContain('email')->not->toContain('worker');
});

it('근로자 토큰으로도 동일하게 동작한다 (앱이 로그인 후에도 확인한다)', function () {
    Sanctum::actingAs(Worker::factory()->create());

    $this->getJson('/api/v1/app/version?version_code=4')
        ->assertOk()
        ->assertJsonPath('update_available', true);
});
