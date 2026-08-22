<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Actions\RequestSupplementAction;
use App\Domains\Recruitment\Actions\ScreenWorkerAction;
use App\Domains\Recruitment\Enums\ScreeningStatus;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use App\Domains\Recruitment\Support\ApplicationDocuments;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * 근로자 가입 신청 큐 (업무흐름 §2).
 *
 * 담당자가 내리는 결정은 넷이다 — 보완 요청 / 합격 / 보류 / 불합격.
 * 셋(합격·보류·불합격)은 ScreenWorkerAction 이, 보완 요청은 메일 발송이 따라붙어
 * RequestSupplementAction 이 맡는다.
 *
 * 합격은 곧 가입 승인이다. 왜 한 번에 처리하는지는 ScreenWorkerAction 주석 참고.
 */
class SignupApprovalController extends Controller
{
    /**
     * 승인 대기 목록 — 선발 진행 상태와 서류 개수를 함께 보여 준다.
     *
     * 서류 개수가 목록에 있어야 하나하나 열어 보지 않고도 보완이 필요한 신청을
     * 골라낼 수 있다.
     */
    public static function rows(): array
    {
        return Worker::with('city:id,name')
            ->withCount('files')
            ->where('status', WorkerStatus::Pending->value)
            ->latest('id')->limit(500)->get()
            ->map(function (Worker $w) {
                $screening = $w->screening();

                return [
                    'id' => $w->id,
                    'name' => $w->name,
                    'email' => $w->email,
                    'nationality' => $w->nationality,
                    // 지역별로 모집 정원이 다르므로 승인 판단에 필요하다
                    'city' => $w->city?->name,
                    'locale' => $w->locale,
                    'files' => $w->files_count,
                    'screening' => $screening->value,
                    'screening_label' => $screening->label(),
                    'tone' => $screening->tone(),
                    'registered' => LocalTime::format($w->created_at),
                    // 표에는 숫자·코드가 아니라 읽을 글자가 필요하다 (엑셀로도 그대로 나간다).
                    'files_label' => $w->files_count > 0 ? $w->files_count.'건' : '없음',
                    'city_label' => $w->city?->name ?? '-',
                    // 편집기가 없는 칸이라 눌러도 셀이 열리지 않는다 → 상세를 여는 자리로 쓴다.
                    'detail' => '상세 ▸',
                ];
            })->all();
    }

    /** 사이드바 배지 — 아직 결정이 안 난 신청 수. */
    public static function openCount(): int
    {
        return Worker::where('status', WorkerStatus::Pending->value)->count();
    }

    /**
     * 보완 요청 항목 후보 — **키 => 한국어 라벨**.
     *
     * 자유 입력이 아니라 고르게 한다. 담당자마다 다르게 적으면 근로자가 받는
     * 안내가 제각각이 되고, 번역도 붙일 수 없다.
     *
     * 저장은 키로 한다. 한국어 라벨을 저장하면 근로자에게 보여 줄 때 기계
     * 번역에 맡기게 되고, 서식 이름은 그쪽이 자주 틀린다.
     *
     * @return array<string, string>
     */
    public static function supplementItems(): array
    {
        return ApplicationDocuments::supplementOptions();
    }

    /** 가입 신청 상세 (본인 정보 + 제출 서류) — 상세 탭용. 개인정보 열람 감사(§7-6). */
    public function show(Worker $worker): JsonResponse
    {
        $worker->recordAccessBy(Auth::user(), 'signup-detail');
        $worker->loadMissing('city');

        $sub = OnboardingSubmission::where('worker_id', $worker->id)->latest('id')->first();
        $hasSig = $sub && filled($sub->signature_path) && Storage::disk('local')->exists($sub->signature_path);
        $screening = $worker->screening();

        return response()->json([
            'id' => $worker->id,
            'name' => $worker->name,
            'email' => $worker->email,
            'nationality' => $worker->nationality,
            'city' => $worker->city?->name,
            'locale' => $worker->locale,
            'status' => $worker->status->value,
            // 심사에 필요한 인적사항을 그대로 보여 준다. 가려 두면 담당자가 값을
            // 다른 곳에 옮겨 적게 된다. 열람 기록은 위에서 남겼다(§7-6).
            'passport_no' => $worker->passport_no,
            'birth_date' => $worker->birth_date,
            'phone_home_country' => $worker->phone_home_country,
            'age' => $worker->age(),
            'registered' => LocalTime::format($worker->created_at),
            'screening' => $screening->value,
            'screening_label' => $screening->label(),
            'tone' => $screening->tone(),
            'screening_note' => $worker->screening_note,
            'screened_at' => $worker->screened_at === null ? null : LocalTime::format($worker->screened_at),
            // 담당자 화면이라 한국어로 푼다. 근로자에게는 각자 언어로 나간다.
            'supplement_items' => ApplicationDocuments::labels($worker->supplement_items ?? []),
            'supplement_requested_at' => $worker->supplement_requested_at === null
                ? null
                : LocalTime::format($worker->supplement_requested_at),
            // 본인이 웹에서 올린 서류. 내려받기는 기존 근로자 서류 라우트를 그대로 쓴다.
            'files' => $worker->files()->latest('id')->get()->map(fn (WorkerFile $f) => [
                'id' => $f->id,
                'name' => $f->original_name,
                'size' => $f->sizeLabel(),
                'uploaded_at' => LocalTime::format($f->created_at),
                'url' => route('admin.workers.files.show', [$worker, $f]),
                'missing' => ! $f->exists(),
            ])->all(),
            'onboarding' => $sub ? [
                'status' => $sub->status->label(),
                'payload' => $sub->payload ?? [],
                'has_signature' => $hasSig,
                'signature_url' => $hasSig ? route('admin.onboarding.signature', $sub) : null,
            ] : null,
        ]);
    }

    /** 합격 / 보류 / 불합격 */
    public function screen(Request $request, Worker $worker, ScreenWorkerAction $action): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in([
                ScreeningStatus::Passed->value,
                ScreeningStatus::Held->value,
                ScreeningStatus::Failed->value,
            ])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->execute(
                $worker,
                ScreeningStatus::from($data['decision']),
                $data['note'] ?? null,
                Auth::user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'status' => $worker->refresh()->status->value]);
    }

    /**
     * 표에서 체크한 신청을 한 번에 심사한다.
     *
     * 표 안에는 버튼을 둘 수 없어(편집기 없는 칸은 글자만 그린다) 체크 → 툴바
     * 순서로 처리한다. 쉰 명이 한꺼번에 들어오는 명단에서 쉰 번 누르지 않아도
     * 되는 편이 낫기도 하다.
     *
     * 한 건이 막혀도 나머지는 진행한다 — 이미 결정 난 건이 섞였다고 통째로
     * 되돌아가면 무엇이 걸렸는지 찾기만 어려워진다.
     *
     * 합격은 계정을 열고 합격 알림을 보낸다(ScreenWorkerAction). 되돌릴 수 없는
     * 동작이라 화면이 몇 명에게 알림이 나가는지 먼저 말한다.
     */
    public function bulkScreen(Request $request, ScreenWorkerAction $action): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in([
                ScreeningStatus::Passed->value,
                ScreeningStatus::Held->value,
                ScreeningStatus::Failed->value,
            ])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:workers,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $decision = ScreeningStatus::from($data['decision']);
        $done = 0;
        $failed = [];

        foreach (Worker::whereIn('id', $data['ids'])->get() as $worker) {
            try {
                $action->execute($worker, $decision, $data['note'] ?? null, Auth::user());
                $done++;
            } catch (RuntimeException $e) {
                // 이름은 적지 않는다 — 메시지가 명단 사본이 되면 안 된다(§7-3).
                $failed[] = '#'.$worker->id.' '.$e->getMessage();
            }
        }

        return response()->json([
            'ok' => true,
            'message' => $failed === []
                ? "{$done}건을 '{$decision->label()}' 처리했습니다."
                : "{$done}건 처리 · ".count($failed).'건 건너뜀 - '.implode(' / ', array_slice($failed, 0, 3)),
            'rows' => self::rows(),
            'open_count' => self::openCount(),
        ]);
    }

    /**
     * 표에서 체크한 신청에 같은 보완 항목을 한 번에 요청한다.
     *
     * 같은 서류가 빠진 사람이 무더기로 나오는 것이 보통이라, 항목을 한 번 고르고
     * 여러 명에게 보낸다. 메일은 각자의 언어로 나간다(RequestSupplementAction).
     */
    public function bulkSupplement(Request $request, RequestSupplementAction $action): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:workers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $done = 0;
        $failed = [];

        foreach (Worker::whereIn('id', $data['ids'])->get() as $worker) {
            try {
                $action->execute($worker, $data['items'], $data['note'] ?? null, Auth::user());
                $done++;
            } catch (RuntimeException $e) {
                $failed[] = '#'.$worker->id.' '.$e->getMessage();
            }
        }

        return response()->json([
            'ok' => true,
            'message' => $failed === []
                ? "{$done}명에게 보완 요청 메일을 보냈습니다."
                : "{$done}명 발송 · ".count($failed).'명 건너뜀 - '.implode(' / ', array_slice($failed, 0, 3)),
            'rows' => self::rows(),
            'open_count' => self::openCount(),
        ]);
    }

    /** 보완 요청 — 부족한 항목을 골라 근로자에게 메일을 보낸다. */
    public function requestSupplement(Request $request, Worker $worker, RequestSupplementAction $action): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->execute($worker, $data['items'], $data['note'] ?? null, Auth::user());
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
