<?php

declare(strict_types=1);

/**
 * 영어 — **사이트를 영어로 보는 방문자용 일부만** 둔다.
 *
 * 근로자의 locale 은 ko/bn/lo/si/vi/ne/ky 중 하나다(RegisterWorkerRequest).
 * 'en' 인 근로자는 없으므로 알림·메일 문구는 여기 필요 없고, 나머지 키는
 * 한국어(fallback)로 떨어져도 실제로 쓰이지 않는다.
 *
 * 여기 있는 것은 **화면에 그대로 박히는 서식 이름**이다. 이 글자들은
 * data-no-translate 라 번역기가 건드리지 않으므로, 영어 화면에서 한국어로
 * 굳지 않으려면 사람이 옮긴 문구가 있어야 한다.
 *
 * ※ translations:check 는 근로자 7개 언어만 본다. 이 파일은 그 대상이 아니다.
 */
return [
    'doc_passport' => 'Passport copy',
    'doc_photo' => 'ID photo',
    'doc_health' => 'Health check result',
    'doc_criminal' => 'Criminal record certificate',
    'doc_birth_date' => 'Date of birth',
    'doc_phone' => 'Phone number in your home country',
    'doc_passport_retake' => 'Retake the passport copy (text is blurred)',
    'doc_other' => 'Other documents',
    'documents_hint' => 'Please take photos of the documents below and upload them. You can still apply without them — we will ask separately if anything is missing.',
];
