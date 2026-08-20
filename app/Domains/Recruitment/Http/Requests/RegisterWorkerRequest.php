<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Requests;

use App\Domains\Demand\Models\City;
use App\Domains\Recruitment\Support\ApplicationDocuments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 근로자 셀프 가입 요청 검증 (CLAUDE.md §9, §11: 입력 검증은 Form Request).
 *
 * 민감 필드(passport_no/birth_date/phone_home_country)는 검증만 하고
 * 저장 시 encrypted cast 로 암호화된다(§7-1). 위치정보는 받지 않는다(§7-2).
 */
class RegisterWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 가입은 비인증 공개 엔드포인트
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:workers,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nationality' => ['required', 'string', 'size:2'],
            // 지원 지자체 — 지역별 모집 정원·조건이 따로 운영되므로 가입 시 확정한다.
            // 선택지는 GET /api/v1/cities 로 내려준다.
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'locale' => ['required', 'in:ko,bn,lo,si,vi,ne,ky'],
            'passport_no' => ['required', 'string', 'max:64'],
            'birth_date' => ['nullable', 'date'],
            'phone_home_country' => ['nullable', 'string', 'max:40'],
            // 가입과 함께 받는 서류. **없어도 접수된다** — 현지에서 스캔본을
            // 바로 구하지 못하는 일이 많다. 부족하면 담당자가 보완을 요청한다.
            ...ApplicationDocuments::rules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ApplicationDocuments::messages();
    }

    /**
     * 지역별 모집 조건 확인 — 모집을 닫았거나 정원이 찬 지역은 받지 않는다.
     *
     * exists 검증을 통과한 뒤에만 의미가 있으므로 after 훅에서 본다.
     * 근로자에게 나가는 문구라 자국어로 낸다(§6).
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('city_id')) {
                    return;
                }

                $city = City::find($this->integer('city_id'));

                if ($city !== null && ! $city->isOpenForSignup()) {
                    $validator->errors()->add(
                        'city_id',
                        trans('worker.city_closed', [], $this->input('locale', 'ko')),
                    );
                }
            },
        ];
    }
}
