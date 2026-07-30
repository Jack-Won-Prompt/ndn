<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Site\Models\SitePage;
use App\Domains\Site\Models\SiteSection;
use Illuminate\Database\Seeder;

/**
 * 회사소개 콘텐츠 초기값.
 *
 * 기존 블레이드 페이지의 문구·이미지를 그대로 옮겼다. 손으로 베끼면 오탈자가
 * 나므로 원본에서 추출해 생성했다.
 *
 * 앞으로 문구를 바꿀 때는 관리자 화면에서 고친다. 이 시더는 처음 한 번
 * 채워 넣는 용도이며, 이미 있는 페이지는 건드리지 않는다.
 */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $data) {
            $sections = $data['sections'] ?? [];
            unset($data['sections']);

            $page = SitePage::firstOrCreate(['key' => $data['key']], $data);

            // 탭 라벨은 나중에 추가된 칸이라, 기존 페이지에도 채워 준다.
            if (blank($page->nav_label)) {
                $page->forceFill(['nav_label' => $data['nav_label']])->save();
            }

            // 이미 채워진 페이지는 그대로 둔다 — 관리자가 고친 내용을 덮으면 안 된다.
            if ($page->sections()->exists()) {
                continue;
            }

            foreach ($sections as $i => $section) {
                SiteSection::create([
                    'site_page_id' => $page->id,
                    'type' => $section['type'],
                    'position' => $i + 1,
                    'payload' => $section['payload'] ?? [],
                ]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function pages(): array
    {
        return [
            [
                'key' => 'home',
                'nav_label' => '홈',
                'title' => '모집부터 귀국까지 하나의 데이터로 관리합니다',
                'lead' => '농가 수요 신청, 송출국 모집과 현지 면접, 입국과 배치, 정착 서비스, 월별 사후관리까지. 흩어져 있던 계절근로자 행정을 N.D.N Korea가 하나의 흐름으로 잇습니다.',
                'hero_image' => 'site/assets/img/hero_greenhouse.jpg',
                'icon' => 'home_rounded',
                'position' => 1,
                'sections' => [
                    [
                        'payload' => [
                            'items' => [
                                [
                                    'title' => 'stats.countries',
                                    'label' => '송출 협력국',
                                ],
                                [
                                    'title' => 'stats.workers',
                                    'label' => '누적 입국 근로자',
                                ],
                                [
                                    'title' => 'stats.cities',
                                    'label' => '협약 지자체',
                                ],
                                [
                                    'title' => 'stats.return_rate',
                                    'label' => '계약 만료 귀국률',
                                ],
                            ],
                        ],
                        'type' => 'stats',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'About N.D.N',
                            'title' => '제도를 아는 사람이 현장을 함께 봅니다',
                            'image' => 'site/assets/img/business_meeting.jpg',
                            'body' => [
                                '계절근로자 제도는 지자체·농가·송출기관·근로자가 각각 다른 서류와 일정으로 움직입니다. 어느 한 곳이 늦으면 입국 일정 전체가 밀립니다.',
                                'N.D.N Korea는 이 네 주체를 같은 화면 위에 올려 두고, 지금 무엇이 막혀 있는지 서로가 볼 수 있게 만듭니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '농가 수요 신청부터 Demand Letter 발행까지 한 번에',
                                ],
                                [
                                    'title' => '송출국 현지 면접 결과와 후보자 이력을 그대로 승계',
                                ],
                                [
                                    'title' => '입국 이후 월별 인터뷰로 이탈 징후를 조기에 확인',
                                ],
                            ],
                        ],
                        'type' => 'split',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'Services',
                            'title' => '여섯 갈래의 일을 한 팀이 맡습니다',
                            'body' => [
                                '모집과 교육, 행정과 생활 지원은 원래 서로 다른 회사가 나눠 하던 일입니다. 나뉘어 있으면 책임도 나뉩니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '모집 & 선별',
                                    'body' => '송출국 현지에서 후보자를 모으고 면접으로 거릅니다. 합격·보류·불합격 사유를 기록으로 남겨 다음 회차에 그대로 씁니다.',
                                ],
                                [
                                    'title' => '사전 교육',
                                    'body' => '한국어, 생활 규칙, 농작업 안전. 입국 전에 알고 오는 것과 와서 배우는 것은 정착 속도가 다릅니다.',
                                ],
                                [
                                    'title' => '현장 관리',
                                    'body' => '배치 이후가 진짜 시작입니다. 점검자가 농가를 방문해 체크인하고, 월별 인터뷰로 상태를 확인합니다.',
                                ],
                                [
                                    'title' => '인재풀 네트워크',
                                    'body' => '송출국 정부·기관과 직접 연결된 채널을 유지합니다. 중간 단계가 줄면 근로자 부담 비용도 줄어듭니다.',
                                ],
                                [
                                    'title' => '행정 지원',
                                    'body' => '비자 서류, 입국 신고, 체류 기간 관리. 담당자가 놓치기 쉬운 기한을 시스템이 먼저 알립니다.',
                                ],
                                [
                                    'title' => '생활 지원',
                                    'body' => '통장 개설, 보험 가입, 통신·유심. 입국 첫 주에 몰리는 일들을 대리점과 연계해 처리합니다.',
                                ],
                            ],
                        ],
                        'type' => 'cards',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'Process',
                            'title' => '다섯 단계, 끊기지 않는 기록',
                            'body' => [
                                '각 단계에서 남긴 정보가 다음 단계로 그대로 넘어갑니다. 같은 내용을 다시 묻지 않습니다.',
                            ],
                            'items' => [
                                [
                                    'label' => 'STEP 01',
                                    'title' => '해외 모집',
                                    'body' => '농가 수요를 모아 송출국에 전달하고 후보자를 모집합니다.',
                                ],
                                [
                                    'label' => 'STEP 02',
                                    'title' => '사전 교육',
                                    'body' => '현지에서 한국어와 안전 교육을 마치고 출국을 준비합니다.',
                                ],
                                [
                                    'label' => 'STEP 03',
                                    'title' => '입국 지원',
                                    'body' => '비자 발급과 항공, 공항 픽업과 이송까지 배차로 관리합니다.',
                                ],
                                [
                                    'label' => 'STEP 04',
                                    'title' => '현장 배치',
                                    'body' => '농가 조건과 근로자 이력을 맞춰 배치합니다. 가족은 함께 묶습니다.',
                                ],
                                [
                                    'label' => 'STEP 05',
                                    'title' => '사후 관리',
                                    'body' => '월별 인터뷰와 현장 점검으로 계약 만료까지 함께합니다.',
                                ],
                            ],
                        ],
                        'type' => 'steps',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'Countries',
                            'title' => '네 개 언어로 같은 내용을 전합니다',
                            'image' => 'site/assets/img/korean_class.jpg',
                            'body' => [
                                '근로자에게 가는 안내문·알림·서류는 모두 모국어로 나갑니다. 한국어만 적힌 종이를 손에 쥐여 주고 이해했으리라 가정하지 않습니다.',
                            ],
                        ],
                        'type' => 'split',
                    ],
                    [
                        'payload' => [
                            'title' => '계절근로자 도입을 준비하고 계신가요',
                            'body' => [
                                '지자체 담당자와 농가 모두 문의할 수 있습니다. 필요한 서류와 일정부터 안내해 드립니다.',
                            ],
                        ],
                        'type' => 'cta',
                    ],
                ],
            ],
            [
                'key' => 'about',
                'nav_label' => '회사소개',
                'title' => '회사소개',
                'lead' => '제도와 현장 사이에서, 양쪽 말을 모두 알아듣는 회사가 필요했습니다.',
                'hero_image' => 'site/assets/img/landscape.jpg',
                'icon' => 'apartment_rounded',
                'position' => 2,
                'sections' => [
                    [
                        'payload' => [
                            'eyebrow' => 'Message',
                            'title' => '함께 성장하는 인력교류 플랫폼',
                            'image' => 'site/assets/img/handshake.jpg',
                            'body' => [
                                '안녕하십니까. 급변하는 글로벌 환경 속에서 대한민국 농업과 어업 현장은 심각한 인력 부족 문제에 직면해 있습니다. 이에 주식회사 앤디앤(NDN Co., Ltd.)은 단순한 인력 공급을 넘어, 외국인 근로자와 대한민국 농·어업 현장이 함께 성장할 수 있는 지속가능한 인력교류 플랫폼을 구축하고자 설립되었습니다.',
                                '당사는 외국인 계절근로자(E-8) 사업을 중심으로 해외 인재 발굴, 한국어 및 문화 교육, 입국 후 생활관리, 근로환경 지원, 귀국 후 사후관리까지 체계적인 운영 시스템을 구축하고 있습니다. 근로자와 고용주 모두가 만족하는 상생 모델로 대한민국 농촌의 경쟁력 강화에 기여하고자 노력하고 있습니다.',
                                '또한 해외 정부기관·지방자치단체·교육기관 및 협력기관과의 긴밀한 네트워크를 통해 투명하고 신뢰할 수 있는 국제 인력교류 체계를 만들어가고 있습니다.',
                                '주식회사 앤디앤 대표이사 전병언',
                            ],
                        ],
                        'type' => 'split',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'Vision',
                            'title' => '세 가지를 지킵니다',
                            'body' => [
                                '송출국 기관과 직접 연결된 채널을 유지합니다. 중간 단계가 줄면 근로자가 부담하는 비용도 함께 줄어듭니다.',
                            ],
                            'items' => [
                                [
                                    'title' => 'Global',
                                    'body' => '송출국 기관과 직접 연결된 채널을 유지합니다. 중간 단계가 줄면 근로자가 부담하는 비용도 함께 줄어듭니다.',
                                ],
                                [
                                    'title' => 'Professional',
                                    'body' => '제도 변경과 서류 요건을 먼저 읽고 반영합니다. 담당자가 공문을 찾아 헤매지 않게 합니다.',
                                ],
                                [
                                    'title' => 'Trustworthy',
                                    'body' => '개인정보는 필요한 범위에서만 다룹니다. 누가 언제 무엇을 열람했는지 기록으로 남깁니다.',
                                ],
                            ],
                        ],
                        'type' => 'cards',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'History',
                            'title' => '연혁',
                            'image' => 'site/assets/img/mou_signing.jpg',
                            'body' => [
                                '외국인 계절근로자 관리·교육 사업 개시, 농업분야 외국인 인력관리 시스템 구축',
                                '방글라데시 현지 NDN 교육서비스센터 설립, 방글라데시 노동국 산하 국영 송출기업 보이셀(BOESL)·중국 협력기관과 MOU, 당진시 외국인근로자 교육·행정서비스 지원 구축',
                                '당진시 주체·NDN 주관, 방글라데시 노동국 계절근로자 공급 협약 체결, 안전교육 프로그램 운영',
                                '현장 모니터링 시스템 운영, 당진시 교육프로그램 참여업체 선정, 지자체·농가 협력사업 확대(당진시 헬프 지원센터 설립)',
                            ],
                        ],
                        'type' => 'split',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'Company',
                            'title' => '사업자 정보',
                        ],
                        'type' => 'rich',
                    ],
                    [
                        'payload' => [
                            'title' => '협업을 논의하고 싶으신가요',
                            'body' => [
                                '지자체, 농협, 송출기관 모두 환영합니다.',
                            ],
                        ],
                        'type' => 'cta',
                    ],
                ],
            ],
            [
                'key' => 'services',
                'nav_label' => '서비스',
                'title' => '서비스',
                'lead' => '모집에서 사후관리까지, 단계마다 무엇을 하는지 구체적으로 적었습니다.',
                'hero_image' => 'site/assets/img/hero_interior.jpg',
                'icon' => 'widgets_rounded',
                'position' => 3,
                'sections' => [
                    [
                        'payload' => [
                            'eyebrow' => '01 — Recruitment',
                            'title' => '모집 & 선별',
                            'image' => 'site/assets/img/interview.jpg',
                            'body' => [
                                '농가가 원하는 조건은 단순히 인원 수가 아닙니다. 품목과 작업 강도, 근무 기간, 성별과 연령대, 형제나 부부가 함께 오는지까지 다릅니다. 수요 신청서에서 이 조건을 먼저 받아 송출국에 그대로 전달합니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '농가별 수요 신청 → 시청 취합 → Demand Letter 발행',
                                ],
                                [
                                    'title' => '송출국 현지 모집 공고 및 서류 접수',
                                ],
                                [
                                    'title' => '현지 면접 및 평가 기록 — 합격 / 보류 / 불합격',
                                ],
                                [
                                    'title' => '보류자는 대기열로 관리해 다음 회차에 재활용',
                                ],
                            ],
                        ],
                        'type' => 'split',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => '02 — Education',
                            'title' => '사전 교육',
                            'body' => [
                                '입국 후에 배우는 것과 입국 전에 알고 오는 것은 정착 속도가 다릅니다. 현지 교육은 출국 준비 기간에 맞춰 진행합니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '한국어 교육',
                                    'body' => '인사와 숫자, 작업 용어, 몸이 아플 때 쓸 표현부터 가르칩니다.',
                                ],
                                [
                                    'title' => '생활 교육',
                                    'body' => '숙소 규칙, 쓰레기 분리, 이웃 관계. 사소해 보이지만 갈등의 대부분이 여기서 시작됩니다.',
                                ],
                                [
                                    'title' => '안전 교육',
                                    'body' => '농기계 조작, 농약 취급, 폭염 대응. 사고는 대개 첫 달에 일어납니다.',
                                ],
                            ],
                        ],
                        'type' => 'cards',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => '03 — Management',
                            'title' => '현장 관리',
                            'image' => 'site/assets/img/farm_guidance.jpg',
                            'body' => [
                                '배치가 끝이 아니라 시작입니다. 점검자가 농가를 직접 방문해 체크인하고, 매달 근로자와 인터뷰합니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '월별 인터뷰 6개 항목 — 급여 수령, 차별, 생활 규칙, 단체 생활, 건강, 이탈 징후',
                                ],
                                [
                                    'title' => '점검자 방문 체크인 기록',
                                ],
                                [
                                    'title' => '민원 접수 — 문의 / 연장 / 조기 귀국',
                                ],
                                [
                                    'title' => '긴급 상황 시 SOS 알림',
                                ],
                            ],
                        ],
                        'type' => 'split',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => '04 — Administration',
                            'title' => '행정과 정착 지원',
                            'body' => [
                                '입국 첫 주에 몰리는 일들입니다. 미리 예약해 두면 근로자가 이틀을 절약합니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '비자 · 입국',
                                    'body' => '서류 준비와 발급 일정 관리, 항공 예약, 공항 픽업 배차까지.',
                                ],
                                [
                                    'title' => '체류 행정',
                                    'body' => '외국인 등록, 체류 기간 관리. 기한이 다가오면 담당자에게 먼저 알립니다.',
                                ],
                                [
                                    'title' => '통장 · 보험',
                                    'body' => '급여 계좌 개설과 보험 가입. 제휴 대리점과 연계해 처리합니다.',
                                ],
                                [
                                    'title' => '통신 · 유심',
                                    'body' => '연락이 닿지 않으면 관리도 불가능합니다. 입국 당일 개통을 목표로 합니다.',
                                ],
                            ],
                        ],
                        'type' => 'cards',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'Scope',
                            'title' => '누가 무엇을 하는지',
                            'body' => [
                                '역할이 겹치거나 비는 구간이 없도록 미리 나눠 둡니다.',
                            ],
                        ],
                        'type' => 'rich',
                    ],
                    [
                        'payload' => [
                            'title' => '어느 단계부터 필요하신가요',
                            'body' => [
                                '전 과정이 아니라 일부 구간만 맡는 것도 가능합니다.',
                            ],
                        ],
                        'type' => 'cta',
                    ],
                ],
            ],
            [
                'key' => 'worker',
                'nav_label' => '근로자 지원',
                'title' => '근로자 지원',
                'lead' => '한국에 오기 전에 알아 두면 좋은 것들을 정리했습니다.',
                'hero_image' => 'site/assets/img/harvest.jpg',
                'icon' => 'volunteer_activism_rounded',
                'position' => 4,
                'sections' => [
                    [
                        'payload' => [
                            'eyebrow' => 'Before Departure',
                            'title' => '입국 전 준비',
                            'image' => 'site/assets/img/culture_class.jpg',
                            'body' => [
                                '출국 전에 끝내 두면 한국에서 보내는 첫 주가 훨씬 수월해집니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '여권 유효기간 확인 — 체류 예정 기간보다 길어야 합니다',
                                ],
                                [
                                    'title' => '사전 교육 이수 — 한국어, 생활 규칙, 산업 안전',
                                ],
                                [
                                    'title' => '본인 정보 입력 — 앱에서 모국어로 직접 작성합니다',
                                ],
                                [
                                    'title' => '건강검진 결과 제출',
                                ],
                                [
                                    'title' => '가족 연락처 등록 — 긴급 상황 시 연락할 곳',
                                ],
                            ],
                        ],
                        'type' => 'split',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'Living in Korea',
                            'title' => '한국 생활 안내',
                            'body' => [
                                '숙소와 급여, 그리고 아플 때 어떻게 하는지.',
                            ],
                            'items' => [
                                [
                                    'title' => '숙소',
                                    'body' => '농가가 제공하는 숙소에서 생활합니다. 입주 전 상태를 함께 확인하고 기록으로 남깁니다.',
                                ],
                                [
                                    'title' => '급여',
                                    'body' => '본인 명의 계좌로 받습니다. 현금이나 타인 계좌로 받는 것은 정상적인 방식이 아닙니다.',
                                ],
                                [
                                    'title' => '건강 · 보험',
                                    'body' => '보험에 가입되어 있습니다. 다치거나 아프면 참지 말고 바로 담당자에게 알리십시오.',
                                ],
                            ],
                        ],
                        'type' => 'cards',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'Support',
                            'title' => '도움이 필요할 때',
                            'body' => [
                                '혼자 참지 마십시오. 모국어로 이야기할 수 있습니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '월별 인터뷰',
                                    'body' => '매달 담당자가 연락합니다. 급여, 차별, 건강, 생활 문제를 이때 이야기하십시오.',
                                ],
                                [
                                    'title' => '민원 접수',
                                    'body' => '문의, 계약 연장, 조기 귀국 요청을 앱에서 접수할 수 있습니다.',
                                ],
                                [
                                    'title' => '긴급 SOS',
                                    'body' => '사고나 위급한 상황에서는 앱의 SOS 버튼을 누르십시오. 즉시 담당자에게 전달됩니다.',
                                ],
                            ],
                        ],
                        'type' => 'cards',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'FAQ',
                            'title' => '자주 묻는 질문',
                            'items' => [
                                [
                                    'title' => '계절근로자로 얼마 동안 일할 수 있나요?',
                                    'body' => '체류 기간은 배정받은 프로그램과 계약 조건에 따라 정해집니다. 정확한 기간은 계약서에 적힌 내용을 확인하십시오.',
                                ],
                                [
                                    'title' => '급여는 어떻게 받나요?',
                                    'body' => '본인 명의 계좌로 입금됩니다. 입국 후 통장 개설을 도와드립니다. 현금으로 받거나 다른 사람 계좌로 받는 것은 정상적인 방식이 아니므로 담당자에게 알려 주십시오.',
                                ],
                                [
                                    'title' => '일하는 농가를 바꿀 수 있나요?',
                                    'body' => '정해진 절차와 사유가 있는 경우에 한해 가능합니다. 먼저 담당자와 상담하십시오. 무단으로 이탈하면 체류 자격에 문제가 생깁니다.',
                                ],
                                [
                                    'title' => '아프거나 다치면 어떻게 하나요?',
                                    'body' => '즉시 농가와 담당자에게 알리십시오. 보험이 적용됩니다. 위급한 경우 앱의 SOS 버튼을 누르십시오.',
                                ],
                                [
                                    'title' => '가족이나 형제와 함께 배치될 수 있나요?',
                                    'body' => '함께 신청한 경우 같은 농가나 인근 농가로 묶어 배치하려 합니다. 다만 농가의 수요 조건에 따라 달라질 수 있습니다.',
                                ],
                                [
                                    'title' => '한국어를 못 해도 괜찮나요?',
                                    'body' => '입국 전 교육에서 기본 표현을 배웁니다. 안내와 알림은 모국어로 제공되며, 상담도 모국어로 가능합니다.',
                                ],
                            ],
                        ],
                        'type' => 'faq',
                    ],
                    [
                        'payload' => [
                            'title' => '더 궁금한 점이 있으신가요',
                            'body' => [
                                '담당자에게 직접 물어보실 수 있습니다.',
                            ],
                        ],
                        'type' => 'cta',
                    ],
                ],
            ],
            [
                'key' => 'partners',
                'nav_label' => '협력기관',
                'title' => '협력기관',
                'lead' => '혼자 할 수 있는 일이 아닙니다. 국내외 기관과 함께 움직입니다.',
                'hero_image' => 'site/assets/img/partnership_meeting.jpg',
                'icon' => 'handshake_rounded',
                'position' => 5,
                'sections' => [
                    [
                        'payload' => [
                            'eyebrow' => 'Domestic',
                            'title' => '국내 협력기관',
                            'body' => [
                                '수요를 취합하고 배치를 확정하는 주체들입니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '지방자치단체',
                                    'body' => '충청남도 당진시, 경상남도 창녕군. 계절근로자 프로그램의 운영 주체로 농가 수요를 취합하고 배정 인원을 확정합니다.',
                                ],
                                [
                                    'title' => '지역 농협 · 농가',
                                    'body' => '당진시 · 창녕군 지역 농협과 농가. 실제 근로가 이루어지는 현장으로 숙소와 작업 환경을 함께 점검합니다.',
                                ],
                                [
                                    'title' => '교육기관',
                                    'body' => '청주대학교, 보건과학대학교, 충청대학교, 신성대학교. 한국어·문화 교육 과정과 유학 서비스를 함께 설계합니다.',
                                ],
                                [
                                    'title' => '산업체 · 협회',
                                    'body' => '대한민국 축산협회, 봉제가공협회, 충청남도 내수면 어업협회 등 농·어·축산 분야 협력.',
                                ],
                            ],
                        ],
                        'type' => 'cards',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'Overseas',
                            'title' => '해외 송출기관',
                            'body' => [
                                '현지 모집과 면접, 출국 준비를 담당하는 정부·공공 파트너입니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '방글라데시 · 보이셀(BOESL)',
                                    'body' => '방글라데시 노동국 산하 국영 인력송출 기업 보이셀(BOESL)과 인력지원·교육·행정서비스 MOU를 체결했습니다. 방글라데시 현지에 NDN 교육서비스센터를 두어 모집·면접·사전교육을 직접 운영합니다.',
                                ],
                                [
                                    'title' => '중국 · 인력개발기관',
                                    'body' => '허베이성 인력개발자원공사(스좌장 인력개발교육원), 산동성 인력지원센터 등과 협력 체계를 구축하여 현지 모집과 교육을 진행합니다.',
                                ],
                            ],
                        ],
                        'type' => 'cards',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'Partner Agency',
                            'title' => '정착 서비스 제휴사',
                            'image' => 'site/assets/img/partnership_mou.jpg',
                            'body' => [
                                '통장, 보험, 통신, 유심. 입국 첫 주에 몰리는 일들을 지역 대리점과 나눠 처리합니다.',
                                '제3자 제공은 동의 범위 안에서만 이루어지며, 동의 이력은 목적별로 따로 보관됩니다.',
                            ],
                            'items' => [
                                [
                                    'title' => '대리점은 자신에게 배정된 건만 조회할 수 있습니다',
                                ],
                                [
                                    'title' => '근로자 동의가 없는 정보는 대리점 화면에 나타나지 않습니다',
                                ],
                                [
                                    'title' => '내려받는 문서에는 대리점명 워터마크가 들어갑니다',
                                ],
                            ],
                        ],
                        'type' => 'split',
                    ],
                    [
                        'payload' => [
                            'title' => '협력 제안을 기다립니다',
                            'body' => [
                                '지자체, 농협, 송출기관, 정착 서비스 대리점 모두 문의하실 수 있습니다.',
                            ],
                        ],
                        'type' => 'cta',
                    ],
                ],
            ],
            [
                'key' => 'contact',
                'nav_label' => '문의',
                'title' => '문의',
                'lead' => '지자체 담당자, 농가, 송출기관, 제휴사 모두 환영합니다.',
                'icon' => 'mail_rounded',
                'position' => 6,
                'sections' => [
                    [
                        'payload' => [
                            'eyebrow' => 'Inquiry',
                            'title' => '문의 양식',
                            'image' => 'site/assets/img/hero_greenhouse.jpg',
                            'body' => [
                                '이 시안에서는 실제로 전송되지 않습니다. 입력하신 내용은 어디에도 저장되지 않았습니다.',
                            ],
                        ],
                        'type' => 'split',
                    ],
                    [
                        'payload' => [
                            'eyebrow' => 'For Workers',
                            'title' => '근로자이신가요',
                            'body' => [
                                '이 양식 말고 앱을 이용하십시오. 모국어로 상담할 수 있고, 긴급할 때는 SOS 버튼을 쓸 수 있습니다.',
                            ],
                        ],
                        'type' => 'rich',
                    ],
                ],
            ],
        ];
    }
}
