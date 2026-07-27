<?php

declare(strict_types=1);

use App\Domains\Support\Models\ChatConversation;
use App\Domains\Support\Models\ChatVisitor;
use App\Domains\Support\Services\ChatService;
use App\Shared\Enums\UserRole;
use Spatie\Permission\Models\Role;

/**
 * 회사소개 사이트 우하단 "문의하기" 실시간 채팅 (익명 방문자 ↔ NDN 관리자).
 */
beforeEach(function () {
    foreach (UserRole::values() as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('방문자가 메시지를 보내면 방문자·대화·쿠키가 생성된다', function () {
    $res = $this->postJson(route('site.chat.message'), ['body' => '문의드립니다. 신청 절차가 궁금해요.']);

    $res->assertOk()->assertJson(['ok' => true]);
    expect($res->json('messages'))->toHaveCount(1);
    expect($res->json('messages.0.mine'))->toBeTrue();

    $visitor = ChatVisitor::first();
    expect($visitor)->not->toBeNull();

    $conv = ChatConversation::first();
    expect($conv->a_type)->toBe('visitor');
    expect($conv->b_type)->toBe('ndn');
    expect($conv->a_id)->toBe($visitor->id);

    // 쿠키가 발급되고 값이 방문자 토큰과 일치 (평문 쿠키 — 암호화 예외)
    expect($res->getCookie('ndn_visitor', false)->getValue())->toBe($visitor->token);
});

it('NDN 관리자 콘솔에서 방문자 대화가 보이고 제목에 방문자 표기가 있다', function () {
    $this->postJson(route('site.chat.message'), ['body' => '안녕하세요']);

    $list = app(ChatService::class)->conversationsFor(['ndn', null, 'ko']);
    expect($list)->toHaveCount(1);
    expect($list->first()['title'])->toContain('방문자');
    expect($list->first()['title'])->toContain('홈페이지 문의');
});

it('관리자 답장을 방문자가 폴링으로 받는다', function () {
    $this->postJson(route('site.chat.message'), ['body' => '신청은 어떻게 하나요?']);
    $visitor = ChatVisitor::first();
    $conv = ChatConversation::first();

    // 관리자(ndn)가 응답
    app(ChatService::class)->send($conv, ['ndn', null, 'ko'], '홈페이지 상단 문의 메뉴에서 접수하시면 됩니다.');

    // 평문 쿠키(암호화 예외)를 요청에 실어 폴링 — 방문자 식별
    $poll = $this->call('GET', route('site.chat.poll'), [], ['ndn_visitor' => $visitor->token], [], ['HTTP_ACCEPT' => 'application/json']);

    $poll->assertOk();
    $messages = $poll->json('messages');
    expect($messages)->toHaveCount(2);
    // 방문자 관점: 첫 메시지는 내 것, 관리자 답장은 상대(mine=false)
    expect($messages[0]['mine'])->toBeTrue();
    expect($messages[1]['mine'])->toBeFalse();
    expect($messages[1]['body'])->toContain('문의 메뉴');
});

it('쿠키 없는 폴링은 빈 결과를 반환한다', function () {
    $this->getJson(route('site.chat.poll'))
        ->assertOk()
        ->assertJson(['ok' => true, 'messages' => []]);
});

it('본문 없이 전송하면 검증 실패', function () {
    $this->postJson(route('site.chat.message'), ['body' => ''])
        ->assertStatus(422);
});
