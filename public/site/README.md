# N.D.N Korea 회사소개 사이트 — 내부 시안

**Blade 뷰**로 서빙된다(정적 HTML 아님). URL: `http://localhost/ndn`, `/about`, `/services`,
`/worker-support`, `/partners`, `/contact`. CSS/JS/폰트/이미지는 정적 에셋으로 유지.

> **외부 공개 금지.** 사진 25장 전부 라이선스가 확보되지 않았고,
> 일부는 타사 행사 사진이거나 스톡 워터마크가 박혀 있습니다. 아래 목록 참조.

### 감사 이후 확인된 예외 (2026-08-04)

대표 확인 결과 `assets/img/` 의 사진 **17장은 자사 보유 사진**이다.
아래 감사 목록의 "전량 교체 대상"은 이 확인으로 해소되었다.

전 페이지에 다시 배치되어 있다 — 페이지 머리 배경(`.nd-pagehero--photo`)과
사진 판(`.nd-plate--photo`), 홈 히어로(`site.hero_image` 설정).

> 단, 아래 감사표에서 **타사 행사 사진으로 지목된 건**(예: 타사 MOU 서명 사진)은
> 소유 여부와 별개로 "자사 협약"처럼 읽히지 않게 배치·설명을 맞출 것.
> 소유하고 있어도 다른 회사의 협약 장면을 자사 실적으로 보이게 쓰면 표시광고 문제가 된다.

### 자사 촬영본 6장 반입 (2026-08-04)

원본은 저장소 밖(`사진/` 폴더, git 제외)에 있고, 폭 1600px·품질 82 로 줄여 넣었다.
**인물 초상 사용 동의는 전원 확보 완료**(대표 확인).

| 파일 | 내용 | 사용처 | 대체한 파일 |
|---|---|---|---|
| `field_workers.jpg` | 밭 작업 근로자 | 근로자 지원 페이지 머리 | worker_consult.jpg |
| `mou_bangladesh.jpg` | 협약 서명(전경) | 회사소개 연혁 판 | ⚠ mou_signing.jpg |
| `training_bangladesh.jpg` | 현지 직업훈련장 | 서비스 · 사전 교육 판 | ⚠ safety_training.jpg |
| `team_meeting.jpg` | 관계자 협의 | 홈 · 소개 판 | business_meeting.jpg |
| `interview_bangladesh.jpg` | 현지 면접·서류 접수 | 서비스 · 모집 판 | interview.jpg |
| `mou_signing_close.jpg` | 협약 서명(근접) | 협력기관 · 제휴 판 | partnership_mou.jpg |

⚠ 표시 2건은 아래 감사표에서 **타사 MOU 사진 · 스톡 워터마크**로 지목됐던 파일이다.
이번 교체로 그 두 건은 화면에서 빠졌다(파일 자체는 아직 img/ 에 남아 있으니 정리 대상).

### 로고

`assets/logo.png` — 자사 로고(480×320). 배경이 **흰색**이라 밝은 지면에서만 쓴다.
헤더에는 이미지, 푸터·로그인 옆판·에러 화면 등 잉크 배경에서는 텍스트 워드마크를 쓴다.
투명 배경 PNG/SVG 를 받으면 `logo-light.*` 로 넣고 어두운 면에도 적용할 수 있다.

## 구성

```
resources/views/site/          ← HTML 은 Blade 로 이전됨
  layout.blade.php             공통 골격(head·draft-bar·header·nav·footer·script)
  home / about / services /
  worker / partners / contact  각 페이지의 <main> 내용만
routes/web.php                 Route::view 6개 (site.home … site.contact)

public/site/                   ← 정적 에셋만 남음
  README.md
  assets/
    css/style.css       디자인 시스템 전부 (단일 파일)
    js/main.js          모바일 메뉴 · ⚠ 배지 토글 · 폼 안내
    fonts/*.woff2       Pretendard Variable (자체 호스팅)
    icons/*.png         512px 투명 PNG 9종
    img/*.jpg           사진 25장 (최대 1600px, JPEG 82%)
```

레이아웃에서 에셋은 `asset('site/assets/…')`, 페이지 이동은 `route('site.*')` 로 참조한다.
네비게이션 활성 표시는 각 라우트가 `['active' => '<key>']` 를 뷰에 주입해 처리.

> Blade 주의: 인라인 섹션은 `@section('title', '…')` 형식을 쓴다. 블록형
> `@section('title')…@endsection` 은 내용이 단어문자로 끝나면(예: `…Korea`) 뒤의
> `@endsection` 이 이메일로 오인돼 닫히지 않는다.

## 폰트

**Pretendard Variable** 을 자체 호스팅한다. CDN 의존이 없어 폐쇄망에서도 동작한다.

- 파일: `assets/fonts/PretendardVariable.woff2` (2.0 MB, 가변 폰트 45–920)
- `@font-face` 는 `style.css` 최상단, `font-display: swap`
- 각 페이지 `<head>` 에 `rel="preload"` 로 미리 받는다
- 폼 컨트롤(`input/select/textarea/button`)은 `font-family` 를 상속하지 않으므로
  전역 규칙으로 따로 지정했다

> 프로덕션에서는 2 MB 전체 폰트 대신 **동적 서브셋**(`woff2-dynamic-subset`) 사용을 권장한다.
> 한글 전체 글리프가 필요 없는 페이지에서 전송량이 크게 줄어든다.

## 디자인 시스템

- 폰트는 전 요소 **Pretendard** (아래 폰트 항목 참조).
- **블랙 + 화이트 무채색.** 브랜드 액센트 색 없음.
- 화면에서 색을 갖는 요소는 셋뿐이다 — 라인 아이콘의 블루(`#50A0E0`),
  접근성 포커스 링(동일 블루), 시안 단계 ⚠ 경고 배지(앰버).
- 강조는 색이 아니라 **대비와 굵기**로 준다. 예: 현재 페이지 표시는
  골드 텍스트가 아니라 흰색 + 밑줄(`box-shadow: inset 0 -2px`).
- 히어로/페이지헤더 배경 사진에는 `filter: grayscale(1)` 을 걸어
  무채색 화면에서 사진만 튀지 않게 했다.
- 토큰은 `style.css` 최상단 `:root` 에 모여 있다.

## 아이콘

원본 `single_icon_*.png` 는 1920×1920 투명 PNG이고 잉크 색은 `#50A0E0` 블루다.
(`Asset_Usage_Guide.md` 는 "블랙+골드"라고 적고 있으나 실물과 다르다. 실물 기준이 맞다.)

빌드 스크립트가 한 일은 **재색상이 아니라 트림과 리사이즈뿐**이다 — 색은 원본 그대로다.

| 파일 | 용도 |
|---|---|
| `recruitment.png` | 모집 · 선별 |
| `education.png` | 교육 서비스 |
| `management.png` | 현장 관리 |
| `network.png` | 인재풀 네트워크 |
| `admin.png` | 행정 지원 |
| `living.png` | 생활 지원 |
| `visa.png` | 입국 지원 |
| `farm.png` | 현장 배치 |
| `aftercare.png` | 사후 관리 |

## ⚠ 사진 감사 결과 — 25장 전량 교체 대상

배지는 화면에도 표시된다. 상단 바의 **경고 표시 끄기** 버튼으로 잠깐 숨길 수 있다
(선택은 `localStorage` 에 남는다).

### 1군 — 스톡 워터마크 (저작권 침해, 명백)

| 파일 | 문제 |
|---|---|
| `hero_greenhouse_korea.jpg` | Dreamstime 워터마크 전면, ID 147618215 |
| `hero_greenhouse_aerial.jpg` | Alamy 워터마크, ID 2XX8Y97 |
| `hero_korea_landscape.jpg` | Vecteezy 워터마크 전면. AI 생성물로 추정 |
| `education_safety_training.jpg` | Vecteezy 워터마크. AI 생성물로 추정 |

### 2군 — 타사 행사 사진 (표시광고법 리스크)

| 파일 | 실제 내용 |
|---|---|
| `company_mou_ceremony.jpg` | Bureau Veritas × 한국선급 해상풍력 MOU, 2022-03-17 부산 |
| `company_mou_signing.jpg` | NEWTECONS × 대한전선 MOU, 하노이. 타사 로고 워터마크 포함 |
| `partnership_korea_belgium.jpg` | 한–벨기에 외교 회담. 송출 협력국과 무관 |
| `recruitment_bangladesh_workers.jpg` | BOESL·HRDK EPS 송출 행사 (정부 기관) |
| `recruitment_korea_workers.jpg` | 위와 동일 행사 |

자사 협약이 아닌 사진을 회사소개에 쓰면 저작권보다 **허위표시** 쪽이 더 위험하다.

### 3군 — 내용이 사실과 다름

| 파일 | 문제 |
|---|---|
| `farm_harvest_workers.jpg` | 베트남 농장(논라 착용). 한국 아님 |
| `management_field_check.jpg` | 위와 **동일 사진** |
| `management_worker_safety.jpg` | 위와 **동일 사진** (크롭만 다름) |
| `farm_greenhouse_workers.jpg` | `farm_workers_korea.jpg` 와 **바이트 단위 동일 파일** |
| `farm_workers_korea.jpg` | 위와 동일 |
| `farm_vegetables_export.jpg` | 베트남 달랏 추정 (Lettuce 수출 상자). 한국 아님 |
| `management_farm_training.jpg` | 미국/유럽 농장 (John Deere, 서구권 인물) |
| `visa_korea_e8.jpg` | **E-2 회화지도 비자** 실물 스캔. E-8 아님. 타인 여권 문서 |
| `visa_application_process.jpg` | **미국** 비자 신청서 (US Social Security Number 칸) |
| `visa_immigration_support.jpg` | 서구권 사무실 상담 장면. 한국 출입국 행정 아님 |
| `partnership_dangjin.jpg` | 당진 솔뫼성지 항공사진. 협약식 아님 |
| `partnership_rural_korea.jpg` | 남해 다랭이마을 추정. 협력기관과 무관 |
| `recruitment_eps_program.jpg` | 사진이 아니라 타 블로그 **썸네일 이미지** |
| `education_korean_class.jpg` | 출처 미확인. 인물 얼굴 식별 가능 — 초상권 미확보 |
| `education_korean_language.jpg` | 해외 한글학교 **아동** 수업. 성인 근로자 교육 아님 |
| `company_handshake.jpg` | 출처·라이선스 미확인 스톡 이미지 |

원본 `photos/` 69장도 같은 경로로 수집된 것으로 보이므로 그대로 쓰지 말 것.

## ⚠ 텍스트 미확정 항목

`○○` 로 표시된 곳은 전부 자리표시자다.

- **통계 4종** (`index.html`) — 협력국 수, 누적 입국자, 협약 지자체, 귀국률
- **연혁 4건** (`about.html`) — 연도·사건 전부
- **사업자 정보** — 대표이사, 사업자등록번호, 주소, 전화, 이메일
- **협력기관명** (`partners.html`) — 지자체·농협·교육기관·송출기관 전부
- **FAQ 답변** (`worker-support.html`) — 체류 기간 등 법령 확인 필요
- **대표 인사말** (`about.html`) — 대표이사 확인 필요

### 협력국 불일치

`CLAUDE.md` 의 지원 언어는 `ko / bn / lo / si / vi` 로 방글라데시·라오스·스리랑카·베트남을 가리킨다.
반면 `page_structure.md` 에는 「방글라데시 보이셀」, 「중국 허베이」가 적혀 있다.
현재 사이트는 전자를 따랐다. **어느 쪽이 맞는지 확인 필요.**

## 남은 작업

1. 사진 25장 교체 — 자체 촬영본이 가장 안전하다. 스톡을 쓴다면 상업용 라이선스 구매 후 사용.
2. 인물이 식별되는 사진은 **초상권 동의서**를 받을 것. 근로자 사진은 특히.
3. `○○` 자리표시자 전부 실제 값으로 교체.
4. **근로자 지원 페이지 다국어화** — `CLAUDE.md` §6 에 따라 근로자 대상 화면은
   5개 언어 전부 필수다. 현재 한국어만 있으므로 번역 없이 공개 불가.
5. 문의 폼 서버 연동 + 개인정보 수집·이용 동의 절차.
6. 개인정보처리방침 / 이용약관 페이지 작성 (현재 `#` 링크).
7. 공개 시 각 페이지 `<meta name="robots" content="noindex, nofollow">` 제거.

## 별건 — 자격증명 노출

`E:\xampp\htdocs\ndn\pusher.txt` 에 Pusher `app_id` / `key` / `secret` 이 평문으로 있다.
앱을 만들면 `.env` 로 옮기고 이 파일은 삭제할 것. 이미 유출되었을 가능성을 고려해
**Pusher 대시보드에서 키를 재발급**하는 편이 안전하다.
