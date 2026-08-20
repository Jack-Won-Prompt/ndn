<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Demand\Models\City;
use App\Domains\Recruitment\Models\Worker;
use Illuminate\Validation\ValidationException;

/**
 * 근로자 본인 정보 수정 (본인이 직접).
 *
 * 두 곳에서 쓴다 — 보완 요청 링크(로그인 전)와 본인 화면(로그인 후). 두 화면이
 * 각자 저장하면 여권번호 중복 검사 같은 규칙이 한쪽에만 생긴다.
 *
 * **빈 값은 지우지 않는다.** 안 적었다는 것과 지우겠다는 것은 다르다. 보완
 * 화면은 민감 항목을 미리 채워 주지 않으므로(§7-1), 빈 칸을 지움으로 받으면
 * 파일만 올리려던 사람의 여권번호가 사라진다.
 *
 * 변경 이력은 **바뀐 항목 이름만** 남긴다. 값을 남기면 감사 로그가 개인정보
 * 보관소가 된다(§7-1).
 */
class UpdateWorkerProfileAction
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException 여권번호가 다른 사람 것과 겹칠 때
     */
    public function execute(Worker $worker, array $data, string $context): Worker
    {
        $locale = $worker->locale ?: 'ko';

        // 여권번호는 사람을 특정하는 값이라 겹치면 안 된다. 평문 비교가 안 되므로
        // blind index 로 본다(§7-1).
        if (filled($data['passport_no'] ?? null)) {
            $taken = Worker::wherePassport((string) $data['passport_no'])
                ->whereKeyNot($worker->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'passport_no' => [trans('worker.passport_taken', [], $locale)],
                ]);
            }
        }

        // 모집이 닫힌 지역으로는 옮길 수 없다. 가입 때와 같은 규칙이다.
        if (filled($data['city_id'] ?? null) && (int) $data['city_id'] !== $worker->city_id) {
            $city = City::find($data['city_id']);

            if ($city === null || ! $city->isOpenForSignup()) {
                throw ValidationException::withMessages([
                    'city_id' => [trans('worker.city_closed', [], $locale)],
                ]);
            }
        }

        $fields = [
            'name', 'nationality', 'locale', 'city_id',
            'passport_no', 'birth_date', 'phone_home_country',
        ];

        $changes = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $data) || blank($data[$field])) {
                continue;
            }

            $value = $field === 'nationality'
                ? strtoupper((string) $data[$field])
                : $data[$field];

            if ((string) $worker->{$field} === (string) $value) {
                continue;
            }

            $worker->{$field} = $value;
            $changes[] = $field;
        }

        if ($changes === []) {
            return $worker;
        }

        $worker->save();

        activity('worker-account')
            ->performedOn($worker)
            // 항목 이름만 남긴다. 값을 남기면 감사 로그가 개인정보 보관소가 된다.
            ->withProperties(['fields' => $changes, 'context' => $context])
            ->log('근로자 본인 정보 수정');

        return $worker;
    }

    /**
     * 두 화면이 함께 쓰는 검증 규칙.
     *
     * 전부 nullable 이다 — 바꾸고 싶은 것만 채우는 화면이라 required 를 걸면
     * 파일만 올리려는 사람이 막힌다.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'locale' => ['nullable', 'in:ko,bn,lo,si,vi,ne,ky'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'passport_no' => ['nullable', 'string', 'max:64'],
            'birth_date' => ['nullable', 'date'],
            'phone_home_country' => ['nullable', 'string', 'max:40'],
        ];
    }
}
