<?php

declare(strict_types=1);

use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Models\ChatVisitor;

/**
 * 앱에서 보내는 문의 (로그인 전).
 *
 * 계정이 없는 농가·지자체가 도입을 물어보는 창구다. 홈페이지 문의와 같은 곳으로
 * 들어가야 담당자가 한 화면에서 본다.
 */
it('로그인 없이 문의를 보낼 수 있다', function () {
    $this->postJson('/api/v1/site/inquiry', [
        'body' => '계절근로자 도입 절차가 궁금합니다.',
        'name' => '한국농장',
        'contact' => '010-1234-5678',
    ])->assertCreated()->assertJsonPath('data.sent', true);

    expect(ChatVisitor::where('name', '한국농장')->exists())->toBeTrue();
});

it('연락처를 본문에 붙여 담당자가 답할 수 있게 한다', function () {
    // 연락처가 대화 밖에 있으면 담당자가 답할 방법을 찾지 못한다.
    $this->postJson('/api/v1/site/inquiry', [
        'body' => '문의합니다.',
        'contact' => '010-1234-5678',
    ])->assertCreated();

    $visitor = ChatVisitor::latest('id')->first();
    $conversation = ChatConversation::query()
        ->where('a_id', $visitor->id)->orWhere('b_id', $visitor->id)
        ->latest('id')->first();

    expect($conversation->messages()->latest('id')->first()->body)
        ->toContain('010-1234-5678');
});

it('두 번째 문의는 같은 대화로 이어진다', function () {
    // 앱은 쿠키를 못 쓰므로 서버가 준 토큰으로 방문자를 잇는다. 이게 없으면
    // 문의할 때마다 새 대화가 생겨 담당자가 맥락을 잃는다.
    $token = $this->postJson('/api/v1/site/inquiry', ['body' => '첫 문의'])
        ->assertCreated()
        ->json('meta.visitor_token');

    expect($token)->not->toBeEmpty();

    $second = $this->postJson('/api/v1/site/inquiry', [
        'body' => '두 번째 문의',
        'visitor_token' => $token,
    ])->assertCreated();

    expect($second->json('meta.visitor_token'))->toBe($token)
        ->and(ChatVisitor::count())->toBe(1);
});

it('알 수 없는 토큰이면 새 방문자로 받는다', function () {
    // 토큰을 위조해도 남의 대화에 끼어들 수 없어야 한다.
    $this->postJson('/api/v1/site/inquiry', [
        'body' => '문의',
        'visitor_token' => str_repeat('z', 48),
    ])->assertCreated();

    expect(ChatVisitor::where('token', str_repeat('z', 48))->exists())->toBeFalse()
        ->and(ChatVisitor::count())->toBe(1);
});

it('내용 없이 보내면 거부한다', function () {
    $this->postJson('/api/v1/site/inquiry', ['name' => '이름만'])
        ->assertUnprocessable();

    expect(ChatVisitor::count())->toBe(0);
});

it('문의 출처가 앱으로 기록된다', function () {
    // 홈페이지 문의와 섞이므로 어디서 왔는지 남겨야 응대에 참고할 수 있다.
    $this->postJson('/api/v1/site/inquiry', ['body' => '문의'])->assertCreated();

    expect(ChatVisitor::latest('id')->first()->first_page)->toBe('app');
});
