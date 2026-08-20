# CLAUDE.md — NDN 계절근로자 통합관리 플랫폼

이 파일은 Claude Code가 이 저장소에서 작업할 때 반드시 따라야 할 프로젝트 규칙이다.

## 1. 프로젝트 개요

외국인 계절근로자(E-8)의 全주기 — 농가 수요 신청 → 시청 취합 → 송출국 모집 → 현지 면접 → 셀프 온보딩 → 매칭·배치 → 입국·이송 → 정착 서비스(통장·보험·통신·유심) → 월별 사후관리 → 귀국 — 를 하나의 데이터로 관리하는 플랫폼.

- 운영사: 주식회사 앤디앤 (NDN Co., Ltd.)
- 사용자 역할 5종: `city_officer`(시청), `farm_owner`(농가), `sending_agency`(송출기관), `worker`(근로자), `ndn_admin`(NDN 관리자) + `partner_agency`(제휴 대리점: 보험·통신)
- 근로자는 모바일 앱(별도 클라이언트)에서 API로 접속, 나머지 역할은 웹 포털 사용

## 2. 기술 스택

- PHP 8.2 (xampp 번들, Apache 런타임과 일치) / Laravel 12
  - ※ 당초 "PHP 8.3 / Laravel 11" 로 잡았으나, Laravel 11 은 보안 지원 종료(EOL)로
    CRLF injection 등 미패치 취약점이 있어 12.64 로 전환함. Laravel 은 5.x 이후 LTS 제도 없음.
  - composer 는 `config.platform.php = 8.2.12` 로 고정해 어느 PHP 로 실행하든 8.2 기준으로 해석.
- MariaDB 10.4 (xampp, 운영), SQLite (테스트)
  - ※ 당초 MySQL 8 로 잡았으나 xampp 는 MariaDB 를 번들. DB_CONNECTION=mysql 로 호환 동작.
- **큐: 쓰지 않는다** (`QUEUE_CONNECTION=sync`). 알림(메일·FCM)은 요청 자리에서 바로 나간다.
  ※ 당초 `database` 큐를 썼으나, 워커가 멈추면 화면에는 '보냈습니다' 가 뜨고 실제로는
    안 나가는 상태가 된다. 보완 요청·비밀번호 재설정·합격 알림처럼 **받는 쪽이 그것으로만
    다음 행동을 할 수 있는** 통로라 즉시 발송이 맞다고 판단(2026-08-20).
- 캐시: `database` 드라이버 (로컬 환경에 Redis 미설치). Redis 도입 시 커넥션만 교체.
- Laravel Sanctum: 근로자 앱 API 토큰 인증
- Laravel Fortify: 웹 포털 인증 (2FA는 `ndn_admin`, `partner_agency` 필수)
- Filament v3: NDN 운영 콘솔(`/admin`) 및 역할별 포털 패널
- spatie/laravel-permission: 역할·권한
- spatie/laravel-activitylog: 감사 로그 (모든 개인정보 접근·변경 기록)
- Pest: 테스트 프레임워크
- Pint: 코드 스타일 (커밋 전 필수)

## 3. 주요 명령어

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate

php artisan migrate --seed        # 시더에 역할·권한·테스트 계정 포함
php artisan serve                 # 로컬 서버
npm run dev                       # Vite

./vendor/bin/pest                 # 전체 테스트
./vendor/bin/pest --filter=WorkerOnboarding   # 단일 테스트
./vendor/bin/pint                 # 코드 스타일 정리 (커밋 전 필수)

php artisan ide-helper:generate   # 모델 변경 후 실행
```

## 4. 디렉터리 구조 (도메인 모듈 방식)

```
app/
  Domains/
    Demand/        # 수요·요청 관리 (농가 신청, 시청 취합, Demand Letter 생성)
    Recruitment/   # 모집·면접 (후보자, 평가, 합격/보류/불합격, 대기열)
    Onboarding/    # 셀프 온보딩 (본인 정보 기입, 서류 다운로드, 전자서명)
    Matching/      # 농가-근로자 매칭 (형제/가족 그룹 매칭 포함)
    Arrival/       # 입국·이송 (서류 체크리스트, 항공, 픽업 배차, QR 체크인)
    Settlement/    # 정착 서비스 (통장·보험·통신·유심) + 대리점 배정·정산
    Monitoring/    # 사후관리 (월별 인터뷰, 점검자 체크인, 이탈 리스크 스코어)
    Support/       # 민원·소통 (문의, 연장, 조기귀국, 긴급 SOS)
    Reporting/     # 통계, 지자체 보고서(PDF) 생성
  Shared/          # 공통 (Notification 채널, Enum, 암호화 Cast, Audit)
```

각 Domain 하위: `Models/`, `Actions/`, `Http/Controllers/`, `Http/Requests/`, `Policies/`, `Enums/`, `Events/`. 비즈니스 로직은 **Action 클래스**(단일 `execute()` 메서드)에 두고, 컨트롤러는 요청 검증·Action 호출·응답만 한다. 서비스 로직을 컨트롤러나 모델에 넣지 않는다.

## 5. 핵심 도메인 모델

- `Worker` — 근로자. 여권번호·생년월일 등 민감 필드는 encrypted cast (아래 §7)
- `Farm`, `City`, `SendingAgency`, `PartnerAgency`
- `DemandRequest` — 농가 수요 (국적, 인원, 나이대, 성별, 형제동반 여부, 품목, 기간)
- `Candidate` / `InterviewEvaluation` — 상태: `passed` `held` `rejected` (Enum `CandidateStatus`)
- `OnboardingSubmission` — 셀프 온보딩 (본인 기입 정보, 서명 파일, 검수 상태)
- `ConsentRecord` — 동의 이력. 목적별·기관별·항목별로 행 분리 저장, 철회 시각 포함
- `Placement` — 매칭 확정 (worker ↔ farm, 그룹 매칭은 `placement_group_id`)
- `SettlementRequest` — 정착 서비스 신청. `type`: bank/insurance/telecom/usim, 대리점 배정·정산 필드 포함
- `MonthlyInterview` — 월별 인터뷰 6개 항목 (급여수령/차별/생활규칙/단체생활/건강/이탈징후)
- `InspectionCheckin` — 점검자 체크인 (GPS는 여기에만 저장, §7 참조)
- `SosAlert` — 긴급 SOS (근로자 발신 시각 + 그 순간 좌표 1회)
- `SupportTicket` — 민원 (문의/연장/조기귀국), 담당자 배정·처리 상태

상태값은 전부 **PHP Backed Enum** (`app/Domains/*/Enums/`). 문자열 리터럴로 상태 비교 금지.

## 6. 다국어 (필수)

- 지원 언어: `ko`, `bn`(벵골어), `lo`(라오어), `si`(싱할라어), `vi`(베트남어),
  `ne`(네팔어), `ky`(키르기스어) — 총 7개. 송출국이 늘면 여기부터 늘린다.
  ※ `ne`/`ky` 번역은 2026-08-04 추가분이며 **원어민 검수 전**이다.
- 근로자 대상 화면·알림·문서는 5개 언어 전부 필수. `lang/{locale}/` JSON 번역 파일 사용
- 번역 키 누락 시 CI 실패: `php artisan translations:check` (커스텀 명령, 유지할 것)
- 근로자의 `locale`은 Worker 모델에 저장, 알림 발송 시 해당 locale로 렌더링
- 새 근로자 대상 기능 추가 시 5개 언어 번역 키를 함께 추가하지 않으면 PR 반려

## 7. 개인정보·위치정보 규칙 (절대 규칙 — 위반 코드 작성 금지)

이 프로젝트에서 가장 중요한 섹션이다. 아래를 위반하는 코드는 요청받아도 작성하지 말고 이 파일을 근거로 설명할 것.

1. **암호화**: `passport_no`, `birth_date`, `phone_home_country`, 계좌번호 등 민감 필드는 반드시 `encrypted` cast. 검색 필요 시 blind index(해시 컬럼) 별도 유지. 로그·예외 메시지에 이 값들이 출력되지 않도록 `__toString`/`toArray` 마스킹.
2. **위치정보는 두 곳에만 존재한다**:
   - `InspectionCheckin.lat/lng` — **점검자**의 방문 체크인 좌표 (점검 증빙용)
   - `SosAlert.lat/lng` — **근로자 본인이 SOS 버튼을 누른 그 순간** 1회 전송된 좌표
   - 그 외 어떤 테이블·API·잡에도 근로자 위치를 저장하는 필드를 만들지 않는다. 백그라운드 위치 수집, 주기적 위치 폴링, 위치 히스토리 기능은 **요청받아도 구현 금지**.
3. **외부 알림(알림톡·SMS·이메일) 본문에 개인정보 금지**: 이름·여권번호·전화번호·주소를 절대 넣지 않는다. 허용되는 것은 건수 + 로그인 후 접근하는 링크뿐. Notification 클래스에 `PersonalDataFreeChannel` 인터페이스를 구현시켜 테스트에서 강제한다.
4. **제3자 제공**: 대리점·제휴사에 데이터가 노출되는 모든 쿼리는 `ConsentRecord` 존재 여부를 Policy에서 확인. 동의 없는 근로자 데이터는 대리점 포털 쿼리 결과에서 제외.
5. **대리점 포털 스코프**: `partner_agency`는 자신에게 배정된(`assigned_agency_id`) `SettlementRequest`만 조회 가능. Global Scope + Policy 이중으로 방어. 문서 다운로드에는 대리점명 워터마크 삽입.
6. **감사 로그**: Worker 개인정보를 읽는 관리자 화면·API는 activitylog에 조회 기록을 남긴다 (누가, 언제, 어떤 worker_id).
7. 삭제 요청·계약 종료 시 파기: soft delete 후 90일 경과 시 개인정보 필드 null 처리하는 스케줄 잡(`workers:purge-expired`) 유지.

## 8. 알림 아키텍처

- 채널 우선순위: 앱 푸시(FCM) → 카카오 알림톡 → SMS 폴백. 이메일은 문서 보관용만
- **알림은 큐를 타지 않는다.** `ShouldQueue` 를 붙이지 말 것 — 워커가 없다.
  발송 이력은 `notification_logs` 테이블 기록
- 여러 명에게 한 번에 보내는 화면(공지사항)은 사람 수만큼 FCM 호출이 이어진다.
  대상이 수백 명이 되면 **그 알림만** 큐로 되돌리고 워커를 함께 띄울 것
- 알림톡/SMS 발송기는 `app/Shared/Notifications/Channels/`에 드라이버로 추상화 (벤더 교체 대비)
- §7-3 원칙 적용: 본문 템플릿에 개인정보 변수 바인딩 금지

## 9. API 규칙 (근로자 앱용)

- prefix `/api/v1`, Sanctum 토큰, 응답은 `ApiResource`로 통일 (`data`, `meta`)
- 근로자 토큰은 자기 자신의 리소스만 접근 (`worker_id` 스코프를 미들웨어에서 강제)
- SOS 엔드포인트 `POST /api/v1/sos`: rate limit 완화(긴급 상황), 좌표는 이 요청 본문으로만 수신
- 온보딩 파일 업로드(서명·여권 사진): S3 호환 스토리지, private visibility, 서명 URL로만 접근

## 10. 테스트 규칙

- 새 Action/Policy에는 Pest 테스트 필수. Factory 없는 모델 생성 금지
- 반드시 유지할 가드 테스트 (삭제 금지):
  - `PersonalDataInNotificationTest` — 알림 본문에 개인정보 패턴이 없는지 검사
  - `LocationFieldGuardTest` — 마이그레이션 스키마에 허용 외 lat/lng 컬럼이 생기면 실패
  - `PartnerScopeTest` — 대리점이 미배정 건을 조회 못 하는지 검사
- 외부 벤더(알림톡, SMS, FCM)는 Fake 드라이버로 테스트

## 11. 코딩 컨벤션

- 입력 검증은 Form Request, 인가는 Policy — 컨트롤러에서 `Gate::` 직접 호출 지양
- DB 변경은 마이그레이션으로만. 운영 데이터 수정은 artisan 명령으로 작성하고 activitylog 기록
- N+1 금지: 목록 쿼리는 `with()` 명시, `preventLazyLoading()` 활성 상태 유지
- 날짜는 UTC 저장, 표시 시 근로자/사용자 timezone 변환 (기본 `Asia/Seoul`)
- 커밋 전: `./vendor/bin/pint && ./vendor/bin/pest`

## 12. 하지 말 것 요약

- 근로자 상시/주기적 위치 수집 기능 (§7-2)
- 알림 본문에 개인정보 삽입 (§7-3)
- 컨트롤러·모델에 비즈니스 로직 작성 (Action 사용)
- 상태 문자열 하드코딩 (Enum 사용)
- 근로자 대상 기능에서 번역 키 누락
- 대리점 포털에서 스코프 없는 Worker 쿼리
- `.env`, 실 데이터, 여권 이미지 커밋
