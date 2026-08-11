<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Http\Controllers\Api;

use App\Domains\Onboarding\Actions\SubmitOnboardingAction;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Http\Resources\OnboardingResource;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Onboarding\Support\OnboardingProfile;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use App\Shared\Support\SignatureImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 근로자 앱 — 셀프 온보딩 (CLAUDE.md §9).
 *
 * 모든 조작은 인증된 Worker 본인의 제출물에 대해서만 이루어진다(스코프 자동 보장).
 */
class OnboardingController extends Controller
{
    /** 본인의 최신 온보딩 제출물 */
    public function show(Request $request): OnboardingResource|JsonResponse
    {
        $submission = $this->latestFor($request);

        if ($submission === null) {
            return response()->json(['data' => null], 200);
        }

        return new OnboardingResource($submission);
    }

    /** 본인 기입 정보 저장 (편집 가능한 제출물이 없으면 새 draft 생성) */
    public function store(Request $request): OnboardingResource
    {
        $request->validate([
            'payload' => ['required', 'array'],
            // 전자서명: base64(data URL 또는 순수 base64) PNG. 서명 캔버스 결과.
            'signature' => ['nullable', 'string'],
            // 성별·생년월일은 매칭 조건 대조에 쓰이므로 형식을 검증한다.
            // 승인 시 workers 컬럼으로 승격된다(OnboardingProfile).
            ...OnboardingProfile::rules(),
        ]);

        // payload 는 자유 형식이라 validated() 로 받으면 규칙이 없는 키(주소·계좌 등)가
        // 전부 잘려 나간다. 검증만 위에서 하고 값은 원본 그대로 쓴다.
        $payload = (array) $request->input('payload');
        $signature = $request->input('signature');

        /** @var Worker $worker */
        $worker = $request->user();

        $submission = $this->latestFor($request);

        // 편집 불가 상태이거나 없으면 새 draft 를 만든다
        if ($submission === null || ! $submission->status->isEditableByWorker()) {
            $submission = new OnboardingSubmission(['status' => OnboardingStatus::Draft]);
            $submission->worker_id = $worker->id;
        }

        $submission->payload = $payload;

        // 전자서명 저장 — private 디스크에 PNG 로 저장하고 경로만 보관(§9)
        $path = SignatureImage::store($signature, 'onboarding/signatures', (string) $worker->id);
        if ($path !== null) {
            $submission->signature_path = $path;
        }

        $submission->save();

        return new OnboardingResource($submission->refresh());
    }

    /** 제출 */
    public function submit(Request $request, SubmitOnboardingAction $action): OnboardingResource|JsonResponse
    {
        $submission = $this->latestFor($request);

        if ($submission === null) {
            return response()->json(['message' => '제출할 온보딩 정보가 없습니다.'], 422);
        }

        try {
            $action->execute($submission);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OnboardingResource($submission->refresh());
    }

    private function latestFor(Request $request): ?OnboardingSubmission
    {
        /** @var Worker $worker */
        $worker = $request->user();

        return OnboardingSubmission::where('worker_id', $worker->id)->latest('id')->first();
    }
}
