<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Support;

use App\Domains\Recruitment\Models\Worker;
use App\Shared\Enums\Gender;

/**
 * 온보딩 payload 중 **근로자 레코드로 승격되는 항목** (업무흐름 §3).
 *
 * 온보딩 payload 는 대부분 자유 형식(주소·계좌 등)이지만, 성별·생년월일은
 * 매칭 조건 대조(§4)에서 SQL·계산에 쓰이므로 workers 컬럼에도 있어야 한다.
 *
 * 근로자가 적은 값을 그대로 신뢰하지 않고 **검수 승인 시점에만** 반영한다
 * (ReviewOnboardingAction). 승인 전에는 payload 에만 남는다.
 */
class OnboardingProfile
{
    /** payload 키 → workers 컬럼 (같은 이름이지만 의도를 명시한다) */
    public const FIELDS = [
        'gender' => 'gender',
        'birth_date' => 'birth_date',
    ];

    /**
     * 제출 검증 규칙. 두 항목 모두 선택 입력이다 — 모르는 값을 억지로 받으면
     * 잘못된 데이터가 매칭에 들어간다.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'payload.gender' => ['nullable', 'string', 'in:male,female'],
            'payload.birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }

    /**
     * 승인된 payload 를 근로자 레코드에 반영한다.
     *
     * 빈 값은 덮어쓰지 않는다 — 근로자가 항목을 비워 냈다고 해서 기존에 확인된
     * 정보를 지우면 안 되기 때문이다.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string> 실제로 갱신된 컬럼명 (감사 로그용)
     */
    public static function applyTo(Worker $worker, array $payload): array
    {
        $changed = [];

        foreach (self::FIELDS as $key => $column) {
            $value = $payload[$key] ?? null;

            if (blank($value)) {
                continue;
            }

            if ($column === 'gender') {
                $gender = Gender::tryFrom((string) $value);

                // 수요의 '무관'은 근로자 성별이 될 수 없다
                if ($gender === null || $gender === Gender::Any) {
                    continue;
                }

                $value = $gender;
            }

            $worker->{$column} = $value;
            $changed[] = $column;
        }

        if ($changed !== []) {
            $worker->save();
        }

        return $changed;
    }
}
