<?php

declare(strict_types=1);

/**
 * 회사 로고 — 어느 면에 얹느냐에 따라 두 벌을 쓴다.
 *
 * 로고 글자는 검정+금색이라 어두운 화면(콘솔 사이드바·로그인 옆판·에러 페이지)에
 * 그대로 얹으면 'ND' 가 배경에 묻는다. 그래서 검정을 흰색으로 바꾼 판이 따로 있다.
 *
 * 사람 눈으로만 확인하면 놓친다 — 파일 이름을 잘못 적어도 화면은 그냥 빈자리로
 * 뜨고, 밝은 판을 어두운 데 얹어도 "왜 흐릿하지" 하고 넘어간다. 그래서
 * **어느 화면이 어느 판을 쓰는지**를 여기서 못 박는다.
 */
it('두 벌 모두 실제로 배포돼 있다', function () {
    // 뷰가 가리키는 이름과 파일 이름이 어긋나면 화면에는 아무 오류도 안 뜬다.
    expect(public_path('site/assets/logo.png'))->toBeFile()
        ->and(public_path('site/assets/logo-light.png'))->toBeFile();
});

it('밝은 면에는 기본 로고를 얹는다', function () {
    $html = view('partials.logo', ['sub' => 'Korea'])->render();

    expect($html)->toContain('site/assets/logo.png')
        ->not->toContain('logo-light.png');
});

it('어두운 면에는 밝은 로고를 얹고 곁글씨도 함께 밝힌다', function () {
    $html = view('partials.logo', ['on' => 'ink', 'sub' => 'Korea'])->render();

    expect($html)->toContain('site/assets/logo-light.png')
        // 곁들인 작은 글씨 기본색은 짙은 회색이라 잉크 판 위에서 사라진다.
        // 이 클래스가 그걸 되돌린다.
        ->and($html)->toContain('nd-logo--ink');
});

it('누를 곳이 없으면 링크로 만들지 않는다', function () {
    // 에러 페이지 아래에 놓인 로고처럼 표시만 하는 자리가 있다.
    // href 없는 <a> 는 키보드로 훑을 때 걸리기만 하고 아무 데도 안 간다.
    $html = view('partials.logo')->render();

    expect($html)->not->toContain('<a ')
        ->and($html)->toContain('<span');
});

it('파일이 바뀌면 주소도 바뀐다', function () {
    // 로고를 갈아 끼웠는데 방문자 브라우저에 옛 로고가 그대로 남는 일을 막는다.
    $html = view('partials.logo')->render();

    expect($html)->toContain('?v='.filemtime(public_path('site/assets/logo.png')));
});

it('어두운 화면들이 밝은 로고를 쓴다', function () {
    // 뷰마다 직접 <img> 를 적던 때로 되돌아가면 여기서 걸린다.
    $ink = [
        'resources/views/admin/login.blade.php',
        'resources/views/portal/login.blade.php',
        'resources/views/portal/layout.blade.php',
        'resources/views/invitations/accept.blade.php',
        'resources/views/errors/layout.blade.php',
    ];

    foreach ($ink as $view) {
        expect(file_get_contents(base_path($view)))
            ->toContain("'on' => 'ink'");
    }

    // 콘솔 사이드바는 ndn.css 를 안 쓰므로 partial 대신 직접 넣는다. 판만 확인한다.
    expect(file_get_contents(base_path('resources/views/admin/shell.blade.php')))
        ->toContain('site/assets/logo-light.png');
});
