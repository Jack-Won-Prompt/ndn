<?php

declare(strict_types=1);

/**
 * 근로자 대상 문자열 (CLAUDE.md §6: 5개 언어 전부 필수).
 * 이 네임스페이스는 translations:check 가 ko/bn/lo/si/vi 간 키 일치를 강제한다.
 */
return [
    'greeting' => '안녕하세요',
    'new_notice' => '새 공지가 :count건 있습니다.',
    'login_to_view' => '자세한 내용은 로그인 후 확인하세요.',
    'sos_received' => 'SOS 요청이 접수되었습니다. 담당자가 곧 연락합니다.',
    'register_pending' => '가입 신청이 접수되었습니다. 관리자 승인 후 로그인할 수 있습니다.',
    'pending_approval' => '아직 승인되지 않은 계정입니다. 관리자 승인을 기다려 주세요.',
    'passport_taken' => '이미 등록된 여권번호입니다. 담당자에게 문의하세요.',
    'city_closed' => '선택하신 지역은 현재 모집이 마감되었습니다. 다른 지역을 선택해 주세요.',
    'documents_required' => '필수 확인 사항에 모두 동의해야 다음으로 진행할 수 있습니다.',

    // 푸시 알림 — 잠금화면에 그대로 뜨므로 개인정보를 넣지 않는다.
    'push_approved_title' => '가입이 승인되었습니다',
    'push_approved_body' => '이제 앱에 로그인할 수 있습니다.',
    'push_rejected_title' => '가입 신청 결과 안내',
    'push_rejected_body' => '자세한 내용은 담당자에게 문의하세요.',
    'push_onboarding_approved_title' => '서류 검수가 완료되었습니다',
    'push_onboarding_rejected_title' => '서류를 다시 확인해 주세요',
    'push_onboarding_body' => '앱에서 확인하세요.',
    'push_placement_title' => '배정이 확정되었습니다',
    'push_placement_body' => '앱에서 배정 내용을 확인하세요.',
    'push_message_title' => '새 메시지가 도착했습니다',
    'push_message_body' => '앱에서 확인하세요.',
    'push_ticket_title' => '민원 처리 상태가 바뀌었습니다',
];
