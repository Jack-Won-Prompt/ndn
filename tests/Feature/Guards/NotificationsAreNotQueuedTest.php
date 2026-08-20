<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Symfony\Component\Finder\Finder;

/**
 * 알림은 큐를 타지 않는다 (CLAUDE.md §2, §8).
 *
 * 이 프로젝트에는 큐 워커가 없다(`QUEUE_CONNECTION=sync`). ShouldQueue 가 붙은
 * 알림은 sync 커넥션에서도 동작하긴 하지만, 커넥션을 database 로 되돌리는 순간
 * **화면에는 '보냈습니다' 가 뜨고 실제로는 안 나가는** 상태가 된다.
 *
 * 보완 요청·비밀번호 재설정·합격 알림은 받는 쪽이 그것으로만 다음 행동을 할 수
 * 있는 통로라, 조용히 사라지는 것이 가장 나쁘다.
 *
 * 절대 삭제 금지 가드 테스트.
 */
it('알림 클래스에 ShouldQueue 가 붙어 있지 않다', function () {
    $files = Finder::create()->files()->in(app_path())->name('*.php');

    $queued = [];
    $checked = 0;

    foreach ($files as $file) {
        $class = 'App\\'.str_replace(
            ['/', '.php'],
            ['\\', ''],
            ltrim(str_replace(app_path(), '', $file->getRealPath()), DIRECTORY_SEPARATOR.'/'),
        );

        if (! class_exists($class)) {
            continue;
        }

        $ref = new ReflectionClass($class);

        if ($ref->isAbstract() && ! $ref->implementsInterface(ShouldQueue::class)) {
            continue;
        }

        // 알림·메일러블만 본다. 다른 곳에서 큐를 쓰게 되면 그때 이 가드를 넓힌다.
        $isNotification = $ref->isSubclassOf(Notification::class);
        $isMailable = $ref->isSubclassOf(Mailable::class);

        if (! $isNotification && ! $isMailable) {
            continue;
        }

        $checked++;

        if ($ref->implementsInterface(ShouldQueue::class)) {
            $queued[] = $class;
        }
    }

    // 스캐너가 망가져 아무것도 못 찾으면 이 가드는 조용히 통과한다. 그걸 막는다.
    expect($checked)->toBeGreaterThan(5, '알림 클래스를 하나도 찾지 못했습니다 — 가드가 헛돌고 있습니다.');

    expect($queued)->toBe(
        [],
        "큐 워커가 없는데 ShouldQueue 가 붙었습니다. 이 알림은 조용히 사라집니다:\n  ".implode("\n  ", $queued)
    );
})->group('guard');

it('.env.example 이 sync 로 잡혀 있다', function () {
    // 테스트 환경은 phpunit.xml 이 sync 로 고정하므로 config 를 봐도 의미가 없다.
    // 새로 배포하는 곳이 무엇을 복사하는지가 실제로 중요하다.
    expect(file_get_contents(base_path('.env.example')))
        ->toContain('QUEUE_CONNECTION=sync')
        ->not->toContain('QUEUE_CONNECTION=database');
})->group('guard');
