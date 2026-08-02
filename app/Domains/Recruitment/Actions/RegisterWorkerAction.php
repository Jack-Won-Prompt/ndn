<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Events\AdminAlertBroadcast;
use Illuminate\Validation\ValidationException;

/**
 * 근로자 셀프 가입 (관리자 승인제).
 *
 * 가입 즉시 status=Pending 으로 생성하며 로그인은 불가하다(LoginWorkerAction).
 * 관리자가 승인(ApproveWorkerAction)하면 Active 로 전환되어 앱을 사용할 수 있다.
 * 여권번호는 blind index 로 중복 가입을 차단한다(§7-1).
 *
 * @see ApproveWorkerAction
 */
class RegisterWorkerAction
{
    /**
     * @param  array<string, mixed>  $data  RegisterWorkerRequest 검증 통과 데이터
     *
     * @throws ValidationException 동일 여권번호로 이미 가입된 경우
     */
    public function execute(array $data): Worker
    {
        $locale = $data['locale'] ?? 'ko';

        // 여권번호 중복 가입 방지 (평문 비교 불가 → blind index)
        if (Worker::wherePassport((string) $data['passport_no'])->exists()) {
            throw ValidationException::withMessages([
                'passport_no' => [trans('worker.passport_taken', [], $locale)],
            ]);
        }

        $worker = Worker::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],          // hashed cast
            'nationality' => strtoupper((string) $data['nationality']),
            'city_id' => $data['city_id'] ?? null,    // 지원 지자체 (지역별 모집)
            'locale' => $locale,
            'status' => WorkerStatus::Pending,        // 승인 대기
            'passport_no' => $data['passport_no'],    // encrypted cast + blind index
            'birth_date' => $data['birth_date'] ?? null,
            'phone_home_country' => $data['phone_home_country'] ?? null,
        ]);

        // 관리자 콘솔 실시간 알림 (개인정보 없이 건수 안내만, §7-3). 실패해도 무시.
        try {
            broadcast(new AdminAlertBroadcast(
                'signup', '새 근로자 가입 신청이 접수되었습니다.', 'signups',
            ));
        } catch (\Throwable $e) {
            // Pusher 미설정/실패 시 무시
        }

        return $worker;
    }
}
