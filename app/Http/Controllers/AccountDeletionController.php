<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Support\Events\AdminAlertBroadcast;
use App\Domains\Support\Models\AccountDeletionRequest;
use App\Shared\Translation\SiteTranslator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * 사용자 계정·데이터 삭제 요청 (Google Play 데이터 삭제 정책 준수).
 *
 * 공개(비로그인) 페이지에서 삭제 요청을 접수한다. 요청은 관리자 콘솔에서 확인·처리하며,
 * 실제 계정 파기는 §7-7(soft delete 후 90일 파기) 절차를 따른다.
 * 접수 시 관리자에게 실시간 알림을 보내되 본문에 개인정보를 넣지 않는다(§7-3).
 */
class AccountDeletionController extends Controller
{
    /** 안내 + 요청 폼 (회사소개 사이트와 동일하게 선택 언어로 자동 번역) */
    public function show(Request $request, SiteTranslator $translator): Response
    {
        $html = view('site.account-deletion', ['active' => ''])->render();
        $locale = (string) $request->session()->get('site_locale', 'ko');

        return response($translator->translateHtml($html, $locale));
    }

    /** 삭제 요청 접수 */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => '삭제 요청 내용에 동의해야 접수됩니다.',
        ]);

        AccountDeletionRequest::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'reason' => $data['reason'] ?? null,
            'status' => AccountDeletionRequest::STATUS_PENDING,
        ]);

        // 관리자 콘솔 실시간 알림 (개인정보 없이 건수 안내만, §7-3). 실패해도 무시.
        try {
            broadcast(new AdminAlertBroadcast(
                'account_deletion', '새 계정 삭제 요청이 접수되었습니다.', 'account-deletions',
            ));
        } catch (\Throwable $e) {
            // Pusher 미설정/실패 시 무시
        }

        return redirect()->route('legal.account-deletion')->with('deletion_ok', true);
    }
}
