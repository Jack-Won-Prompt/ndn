<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| FCM (Firebase Cloud Messaging) 설정
|--------------------------------------------------------------------------
|
| 앱이 플레이스토어를 거치지 않는 사이드로딩 배포라 스토어 알림이 없다.
| SOS·승인 결과·새 메시지를 앱이 꺼져 있을 때도 전하려면 푸시가 유일한 수단이다.
|
| HTTP v1 API 를 쓴다. 예전의 "서버 키(Legacy)" 방식은 Google 이 폐지했다.
| 인증은 서비스 계정 JSON 으로 하며, 이 파일 하나면 프로젝트의 모든 기기에
| 푸시를 보낼 수 있으므로 자격증명으로 취급한다(git 제외, 권한 600 권장).
|
| 키를 놓으면 자동으로 켜진다 — 별도 on/off 플래그를 두지 않는다.
| 키가 없으면 발송을 건너뛰고 조용히 로그만 남긴다(로컬·테스트 환경).
|
*/

return [

    /** 서비스 계정 JSON 경로. 상대경로는 base_path 기준. */
    'credentials' => env('FCM_CREDENTIALS', 'storage/app/firebase/service-account.json'),

    /**
     * 프로젝트 ID. 비워 두면 서비스 계정 JSON 의 project_id 를 쓴다.
     * (두 곳에 적어 두면 어긋났을 때 원인을 찾기 어렵다.)
     */
    'project_id' => env('FCM_PROJECT_ID'),

    /** 발송 타임아웃(초). 알림 하나 때문에 요청이 오래 붙잡히지 않게 짧게 둔다. */
    'timeout' => (int) env('FCM_TIMEOUT', 10),

    /**
     * 안드로이드 알림 채널 ID. 앱의 채널 생성값과 **반드시 같아야** 소리·중요도가 적용된다.
     * 다르면 Android 8+ 에서 기본 채널로 떨어져 무음으로 뜬다.
     */
    'android_channel' => env('FCM_ANDROID_CHANNEL', 'ndn_default'),

    /** 긴급(SOS) 전용 채널 — 별도 채널이라야 사용자가 소리를 따로 켜 둘 수 있다. */
    'android_channel_urgent' => env('FCM_ANDROID_CHANNEL_URGENT', 'ndn_urgent'),

];
