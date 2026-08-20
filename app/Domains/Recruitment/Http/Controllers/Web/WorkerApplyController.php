<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Controllers\Web;

use App\Domains\Demand\Models\City;
use App\Domains\Recruitment\Actions\RegisterWorkerAction;
use App\Domains\Recruitment\Actions\UpdateWorkerProfileAction;
use App\Domains\Recruitment\Enums\ScreeningStatus;
use App\Domains\Recruitment\Enums\WorkerFileType;
use App\Domains\Recruitment\Http\Requests\RegisterWorkerRequest;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 웹 근로자 가입 (업무흐름 §2).
 *
 * 앱을 깔 수 없는 환경(현지 PC방·송출기관 사무실)에서도 지원할 수 있어야 해서
 * 웹에도 같은 입구를 낸다. 저장은 앱과 **같은 Action** 을 탄다 — 여권번호 중복
 * 차단·지역 모집 마감 확인이 두 경로에서 어긋나면 안 된다.
 *
 * 화면 문구는 회사소개 사이트 레이아웃을 쓰므로 방문자가 고른 언어로 자동 번역된다(§6).
 */
class WorkerApplyController extends Controller
{
    /**
     * 가입 때 함께 받는 서류 안내.
     *
     * **막지는 않는다.** 현지에서 스캔본을 바로 구하지 못하는 경우가 많아, 파일이
     * 없어도 접수는 되고 담당자가 보완을 요청한다. 유형도 고르게 하지 않는다 —
     * 어떤 서류인지 근로자가 판단하기 어렵고, 잘못 고른 분류가 오히려 방해가 된다.
     */
    public const EXPECTED_DOCUMENTS = ['여권 사본', '범죄경력 증명서', '근로 동의서'];

    /** 한 번에 올릴 수 있는 파일 수. */
    public const MAX_FILES = 10;

    /** 가입 폼 */
    public function create(): View
    {
        return view('site.apply', $this->formData());
    }

    /** 가입 접수 */
    public function store(RegisterWorkerRequest $request, RegisterWorkerAction $action): RedirectResponse
    {
        $this->validateFiles($request);

        $worker = $action->execute($request->validated());

        $worker->forceFill(['screening_status' => ScreeningStatus::Received])->save();

        $this->storeFiles($request, $worker);

        return redirect()->route('site.apply.done');
    }

    /** 접수 완료 안내 */
    public function done(): View
    {
        return view('site.apply-done');
    }

    /**
     * 보완 제출 화면 — 메일의 서명 링크로만 들어온다.
     *
     * 이미 쓴 내용은 그대로 두고 부족한 것만 채우게 한다. 로그인 없이 열리므로
     * 여권번호·생년월일 같은 기존 값은 **보여 주지 않는다**(§7-1). 링크가 새어도
     * 읽히는 것이 없어야 한다.
     */
    public function supplement(Worker $worker): View
    {
        abort_unless($worker->status->isPending(), 410, '이미 처리된 신청입니다.');

        return view('site.apply-supplement', [
            'worker' => $worker,
            // 제출 주소도 서명해서 넘긴다. 라우트 이름만으로 만들면 서명이 없어
            // signed 미들웨어가 막는다 — 화면은 열리는데 제출만 안 되는 상태가 된다.
            // 유효기간은 짧게 잡는다. 이 사람은 지금 이 화면에 있다.
            'action' => URL::temporarySignedRoute(
                'site.apply.supplement.store',
                now()->addHours(4),
                ['worker' => $worker->id],
            ),
            'items' => $worker->supplement_items ?? [],
            'note' => $worker->screening_note,
            // 민감하지 않은 값만 미리 채운다. 여권번호·생년월일·전화는 로그인 없이
            // 열리는 화면이라 **되돌려 보여 주지 않는다**(§7-1) — 대신 새로 적으면 바뀐다.
            'cities' => $this->openCities(),
            'prefill' => [
                'name' => $worker->name,
                'nationality' => $worker->nationality,
                'locale' => $worker->locale,
                'city_id' => $worker->city_id,
            ],
            'expected' => self::EXPECTED_DOCUMENTS,
            'maxFiles' => self::MAX_FILES,
            'maxKb' => WorkerFile::MAX_KB,
            'mimes' => WorkerFile::MIMES,
        ]);
    }

    /** 보완 제출 접수 */
    public function storeSupplement(Request $request, Worker $worker, UpdateWorkerProfileAction $profile): RedirectResponse
    {
        abort_unless($worker->status->isPending(), 410, '이미 처리된 신청입니다.');

        $data = $request->validate(
            UpdateWorkerProfileAction::rules() + ['note' => ['nullable', 'string', 'max:1000']]
        );

        $this->validateFiles($request);

        // 빈 값으로 기존 내용을 덮어쓰지 않는다 — 안 적었다고 지우면 안 된다.
        $profile->execute($worker, $data, 'apply-supplement');

        $this->storeFiles($request, $worker, $data['note'] ?? null);

        // 다시 담당자 차례다. 요청 상태를 걷어 접수 줄로 되돌린다.
        $worker->forceFill([
            'screening_status' => ScreeningStatus::Received,
            'supplement_items' => null,
            'supplement_requested_at' => null,
        ])->save();

        activity('worker-account')
            ->performedOn($worker)
            ->withProperties(['files' => count($request->file('documents') ?? [])])
            ->log('가입 서류 보완 제출');

        return redirect()->route('site.apply.done')->with('supplemented', true);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'cities' => $this->openCities(),
            'expected' => self::EXPECTED_DOCUMENTS,
            'maxFiles' => self::MAX_FILES,
            'maxKb' => WorkerFile::MAX_KB,
            'mimes' => WorkerFile::MIMES,
        ];
    }

    /**
     * 모집이 열려 있는 지역만. 닫힌 지역을 보여 주면 다 쓰고 나서 막힌다.
     *
     * @return array<int, array{value:int,label:string}>
     */
    private function openCities(): array
    {
        return City::query()->orderBy('region')->orderBy('name')->get()
            ->filter(fn (City $c) => $c->isOpenForSignup())
            ->map(fn (City $c) => ['value' => $c->id, 'label' => $c->label()])
            ->values()->all();
    }

    /** 파일 검증 — 개수·형식·크기. 없어도 통과한다. */
    private function validateFiles(Request $request): void
    {
        $request->validate([
            'documents' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'documents.*' => ['file', 'mimes:'.WorkerFile::MIMES, 'max:'.WorkerFile::MAX_KB],
        ], [
            'documents.max' => '파일은 한 번에 '.self::MAX_FILES.'개까지 올릴 수 있습니다.',
        ]);

        if (count($request->file('documents') ?? []) > self::MAX_FILES) {
            throw ValidationException::withMessages([
                'documents' => ['파일은 한 번에 '.self::MAX_FILES.'개까지 올릴 수 있습니다.'],
            ]);
        }
    }

    /**
     * 올린 파일을 근로자 서류로 저장한다.
     *
     * 유형은 '기타' 로 둔다 — 근로자에게 분류를 시키지 않기로 했고, 잘못 붙은
     * 분류는 없느니만 못하다. 담당자가 콘솔에서 보고 정리한다. 원본 파일명은
     * 그대로 남기므로 무엇인지 알아볼 수 있다.
     */
    private function storeFiles(Request $request, Worker $worker, ?string $note = null): void
    {
        foreach ($request->file('documents') ?? [] as $file) {
            /** @var UploadedFile $file */
            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
            // 저장 이름은 ASCII 로 만든다. 한글 파일명은 서버·백업에서 깨진다.
            $name = 'apply_'.Str::random(16).'.'.$ext;

            $path = $file->storeAs(
                WorkerFile::DIR.'/'.$worker->id,
                $name,
                ['disk' => WorkerFile::DISK],
            );

            WorkerFile::create([
                'worker_id' => $worker->id,
                'type' => WorkerFileType::Other,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => Storage::disk(WorkerFile::DISK)->size($path),
                'mime' => $file->getClientMimeType(),
                'note' => $note,
                // 본인이 올렸다. 관리자가 올린 것과 구분되도록 비워 둔다.
                'uploaded_by' => null,
            ]);
        }
    }
}
