# NDN 플랫폼 구현 점검 — 업무흐름 vs 실제 소스 (1:1)

`NDN_영역별_업무흐름_정리.md`의 10개 영역·공통 원칙을 실제 코드베이스와 대조한 결과.
기준일: 2026-07-24 · 범례: ✅ 구현 / ⚠ 부분 / ❌ 미구현

> 요약: **개인정보·위치·동의·API·감사 인프라와 온보딩은 실동작**하며, 대부분의
> **운영 워크플로(모집·매칭·입국·정착보드·월별점검·민원티켓·보고서)는 스캐폴딩 단계**다.
> 아래는 착수 우선순위 판단용 상세 표.

---

## 1. 수요·요청 관리 — ⚠ 부분

| 세부 | 상태 | 소스 / 갭 |
|---|---|---|
| 농가 신청 폼 조건(국적·인원·나이·성별·형제·품목·기간) | ⚠ | `DemandRequest`(모델·마이그레이션) 필드 존재. **숙소 형태 필드 없음**. 실제 농가 포털 폼 UI 없음(Action·컨트롤러·테스트만) |
| 시청 취합 대시보드·승인 | ❌ | `DemandRequestPolicy::aggregate()` 자리만. 취합 화면·승인 Action 없음 |
| Demand Letter 자동 생성 | ❌ | `DemandStatus::LetterIssued` enum 값만 존재. 생성 로직·PDF 없음 |
| 송출국 공식 발송 | ❌ | 미구현 |
| 상태 추적(신청→접수→송출국전달→모집중→배정완료) | ⚠ | enum은 draft/submitted/aggregated/letter_issued/rejected — **문서의 5단계와 라벨 불일치** |
| 기록: 취합·승인 이력, 수요서 발송일시 | ❌ | 미구현 |

## 2. 모집·면접·선발 — ❌ 대부분 미구현

| 세부 | 상태 | 소스 / 갭 |
|---|---|---|
| 후보자(Candidate)·평가(InterviewEvaluation) 모델 | ❌ | `app/Domains/Recruitment/Models` 에 `Worker`만. Candidate/평가/`CandidateStatus`(passed/held/rejected) 없음 |
| 모바일 평가 시트·자동 분류 | ❌ | 미구현 |
| 보류 대기열·순번·자동 충원 알림 | ❌ | 미구현 |
| 평가 아카이브·재지원 이력 조회 | ❌ | 미구현 |
| 송출기관 엑셀 일괄 업로드 | ❌ | 미구현 |

## 3. 근로자 셀프 온보딩 — ✅ 핵심 구현 / ⚠ 주변

| 세부 | 상태 | 소스 / 갭 |
|---|---|---|
| 제출물(본인기입·서명·검수상태) | ✅ | `OnboardingSubmission`(payload `encrypted:array`, `signature_path`, status) |
| 동의 이력(목적·기관·항목별, 철회시각) | ✅ | `ConsentRecord` + `ConsentPurpose` + `GrantConsentAction`/`RevokeConsentAction` + `Worker::hasActiveConsent()` |
| 검수(승인/보완/반려) | ⚠ | `ReviewOnboardingAction`(승인/반려). "보완 요청" 별도 상태 없음(반려로 처리). 콘솔은 목록만, 검수 버튼 없음 |
| 앱 제출/조회 | ✅ | `/api/v1/onboarding` (GET·POST·submit), `OnboardingController` |
| 여권 OCR 자동인식 | ❌ | 미구현 |
| 동의서·서약서 5개언어 병기 다운로드 | ❌ | 미구현 |
| 전자서명 / 서명본 촬영 업로드 | ❌ | `signature_path` 필드만. 업로드·서명 URL 흐름 없음 |
| 온보딩 데이터 후속 단계 재활용 | ❌ | 매칭·입국·정착 단계 미구현이라 연결 없음 |

## 4. 매칭·확정 — ❌ 미구현

| 세부 | 상태 | 소스 / 갭 |
|---|---|---|
| `Placement`·그룹매칭(`placement_group_id`) | ❌ | `app/Domains/Matching` 폴더만(빈) |
| 매칭 엔진(조건 대조 추천) | ❌ | 미구현 |
| 확정 다국어 알림 | ❌ | 미구현 |
| 서류 체크리스트(여권·E-8·항공권) 추적 | ❌ | 미구현 |
| 입국 일정 캘린더 | ❌ | 미구현 |

## 5. 입국·이송·배치 — ❌ 미구현

| 세부 | 상태 | 소스 / 갭 |
|---|---|---|
| 공항 픽업·QR 체크인 | ❌ | `app/Domains/Arrival` 폴더만(빈) |
| 배차·농가 인계 체크인 | ❌ | 미구현 |
| 한국 생활 체크리스트(과제) | ❌ | 미구현 |

## 6. 정착 지원 부가 서비스 — ⚠ 골격만

| 세부 | 상태 | 소스 / 갭 |
|---|---|---|
| 신청(bank/insurance/telecom/usim) | ⚠ | `SettlementRequest`(type·assigned_agency_id·status) 모델만. 앱 신청 화면·API 없음 |
| 대리점 스코프(배정 건만) | ✅ | `PartnerAgencyScope`(Global Scope) + `PartnerScopeTest` 통과 |
| 칸반 처리보드·배정·SLA·정산 | ❌ | 미구현 |
| 대리점 웹 포털·워터마크 다운로드 | ❌ | 미구현 |
| 동의 없는 데이터 제외 | ⚠ | `hasActiveConsent()` 헬퍼 존재, 포털 쿼리 미연결 |

## 7. 사후관리·점검 — ⚠ 골격만

| 세부 | 상태 | 소스 / 갭 |
|---|---|---|
| 점검자 체크인(GPS 증빙) | ✅ | `InspectionCheckin`(lat/lng) 모델 + `LocationFieldGuardTest` 화이트리스트 |
| 월별 인터뷰 6개 항목 | ❌ | `MonthlyInterview` 모델 없음 |
| 이탈 리스크 스코어(행동 신호) | ❌ | 미구현 |
| 재점검 워크플로 | ❌ | 미구현 |
| 긴급 24시간 대응 절차 | ❌ | 미구현 |

## 8. 민원·소통 (근로자 앱) — ⚠ SOS만

| 세부 | 상태 | 소스 / 갭 |
|---|---|---|
| 긴급 SOS(누른 순간 좌표 1회) | ✅ | `SosAlert`(lat/lng) + `CreateSosAlertAction` + `POST /api/v1/sos` + `StoreSosRequest` |
| 민원 티켓(신고/문의/연장/조기귀국) | ❌ | `SupportTicket` 모델 없음 |
| 자동 번역 채팅 | ❌ | 미구현 |
| 정보 허브(사전교육·규칙·경보 푸시) | ❌ | 미구현 |
| 알림 본문 개인정보 금지 | ✅ | `PersonalDataFreeChannel` + `PersonalDataInNotificationTest` |

## 9. 귀국·사후 관리 — ❌ 미구현 / ⚠ 파기

| 세부 | 상태 | 소스 / 갭 |
|---|---|---|
| 귀국 일정·항공권 확인 | ❌ | 미구현 |
| 인재풀 DB·재초청 우선순위 | ❌ | 미구현 |
| 파기(soft delete 후 90일 민감필드 파기) | ⚠ | `Worker` softDeletes·`deleted_at` 인덱스 존재. **`workers:purge-expired` 스케줄 잡 미구현** (CLAUDE.md §7-7) |

## 10. 데이터·보고 — ⚠ 부분

| 세부 | 상태 | 소스 / 갭 |
|---|---|---|
| 통합 대시보드 | ⚠ | 콘솔 대시보드 기본 4지표(근로자·수요·온보딩·SOS)만. 국가별·이탈률·민원·만족도 없음 |
| 지자체 보고 PDF 자동 출력 | ❌ | `app/Domains/Reporting` 폴더만(빈) |
| 감사 추적(개인정보 조회·변경) | ✅ | `spatie/activitylog` + `LogsPersonalDataAccess`(콘솔 근로자 열람 시 기록) |
| 데이터 선순환(예측) | ❌ | 미구현 |

---

## 부록 A. 공통 준수 원칙 — 대체로 이행

| 원칙 | 상태 | 근거 |
|---|---|---|
| 소통 일원화(NDN 창구) | ⚠ | 설계 원칙. API가 근로자→NDN 단방향 구조 |
| 개인정보(암호화·동의·알림 본문) | ✅ | `encrypted` cast·blind index·마스킹·`ConsentRecord`·`PersonalDataFreeChannel` + 가드 테스트 |
| 위치정보(2곳만) | ✅ | `InspectionCheckin`·`SosAlert`만. `LocationFieldGuardTest`가 그 외 lat/lng 차단 |
| 다국어(5개 언어) | ⚠ | `lang/{ko,bn,lo,si,vi}/worker.php` + `translations:check`. 근로자 화면 전반 번역은 미완 |
| 자격 준수(대리점 연계) | ⚠ | `SettlementRequest` 구조만. 연계 흐름 미구현 |
| 증빙(사유 기록) | ⚠ | 감사로그 존재. Demand 반려·매칭 변경 사유 필드 미비 |

## 부록 B. 로드맵 대비 현재 위치

- **1단계(MVP: 사후관리 디지털화)**: ⚠ 부분 — SOS·감사·대시보드 골격은 있으나 **월별 점검 폼·민원 티켓 미구현**
- **2단계(수요·온보딩·매칭·정착)**: ⚠ 부분 — **온보딩·동의·수요 모델은 실동작**, 매칭·입국·정착 처리보드 미구현
- **3단계(포털·인재풀·보고 자동화)**: ❌ 미착수

---

## 다음 착수 권장 순위 (MVP 관점)

1. **월별 인터뷰(§7)** — `MonthlyInterview` 6개 항목 + 점검자 체크인 연동 → MVP 핵심
2. **민원 티켓(§8)** — `SupportTicket`(신고/문의/연장/조기귀국) + 앱 API
3. **모집·평가(§2)** — `Candidate`/`InterviewEvaluation`/`CandidateStatus` + 대기열
4. **정착 처리보드(§6)** — 칸반 배정 + 대리점 포털(동의 게이팅)
5. **파기 잡(§7-7)** — `workers:purge-expired` 스케줄 커맨드
6. **지자체 보고 PDF(§10)** — Reporting 도메인
