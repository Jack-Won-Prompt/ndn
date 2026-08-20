<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Controllers\Web;

use App\Domains\Demand\Models\City;
use App\Domains\Recruitment\Actions\RegisterWorkerAction;
use App\Domains\Recruitment\Actions\UpdateWorkerProfileAction;
use App\Domains\Recruitment\Enums\ScreeningStatus;
use App\Domains\Recruitment\Http\Requests\RegisterWorkerRequest;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use App\Domains\Recruitment\Support\ApplicationDocuments;
use App\Http\Controllers\Controller;
use App\Shared\Translation\Concerns\RendersInWorkerLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
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
    use RendersInWorkerLocale;

    /**
     * 가입 때 함께 받는 서류 안내.
     *
     * **막지는 않는다.** 현지에서 스캔본을 바로 구하지 못하는 경우가 많아, 파일이
     * 없어도 접수는 되고 담당자가 보완을 요청한다. 유형도 고르게 하지 않는다 —
     * 어떤 서류인지 근로자가 판단하기 어렵고, 잘못 고른 분류가 오히려 방해가 된다.
     */
    public const MAX_FILES = ApplicationDocuments::MAX_FILES;

    /** 가입 폼 */
    public function create(): Response
    {
        // 방문자가 헤더에서 고른 언어로 보여 준다(§6). 아직 누구인지 모른다.
        return $this->renderLocalized('site.apply', $this->formData());
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
    public function done(): Response
    {
        return $this->renderLocalized('site.apply-done');
    }

    /**
     * 보완 제출 화면 — 메일의 서명 링크로만 들어온다.
     *
     * 이미 쓴 내용을 채워서 보여 준다 — 무엇이 들어가 있는지 모르면 무엇을
     * 고쳐야 할지도 알 수 없다.
     *
     * 로그인 없이 열리는 화면이지만 들어오는 길은 **본인 메일로만 간 기한부
     * 서명 링크**(14일)뿐이고, 보이는 것은 자기 자료뿐이다. 링크가 새면 그
     * 사람의 정보가 보인다는 뜻이라, 기한을 짧게 두는 것이 이 화면의 방어다.
     */
    public function supplement(Worker $worker): Response
    {
        abort_unless($worker->status->isPending(), 410, '이미 처리된 신청입니다.');

        // 누구인지 아는 화면이다. **그 근로자가 고른 언어**로 보여 준다.
        return $this->renderLocalized('site.apply-supplement', [
            'worker' => $worker,
            // 제출 주소도 서명해서 넘긴다. 라우트 이름만으로 만들면 서명이 없어
            // signed 미들웨어가 막는다 — 화면은 열리는데 제출만 안 되는 상태가 된다.
            // 유효기간은 짧게 잡는다. 이 사람은 지금 이 화면에 있다.
            'action' => URL::temporarySignedRoute(
                'site.apply.supplement.store',
                now()->addHours(4),
                ['worker' => $worker->id],
            ),
            // 담당자가 고른 항목은 키로 저장돼 있다. **이 사람 언어로** 풀어 준다 —
            // 무엇을 내야 하는지 못 읽으면 링크를 보낸 의미가 없다.
            'items' => ApplicationDocuments::labels(
                $worker->supplement_items ?? [], $worker->locale
            ),
            'note' => $worker->screening_note,
            // 이미 낸 내용을 보여 준다. 무엇이 들어가 있는지 모르면 무엇을 고쳐야
            // 할지도 알 수 없다. 이 링크는 본인 메일로만 가고 기한이 있다(14일).
            'cities' => $this->openCities(),
            'prefill' => [
                'name' => $worker->name,
                'nationality' => $worker->nationality,
                'locale' => $worker->locale,
                'city_id' => $worker->city_id,
                'passport_no' => $worker->passport_no,
                'birth_date' => $worker->birth_date,
                'phone_home_country' => $worker->phone_home_country,
            ],
            'expected' => ApplicationDocuments::expected($worker->locale),
            'maxFiles' => self::MAX_FILES,
            'maxKb' => WorkerFile::MAX_KB,
            'mimes' => WorkerFile::MIMES,
        ], $worker);
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
            // 가입 화면은 아직 누구인지 모른다 — 방문자가 고른 언어로.
            'expected' => ApplicationDocuments::expected($this->displayLocale()),
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
        $request->validate(ApplicationDocuments::rules(), ApplicationDocuments::messages());

        // 규칙만으로는 놓치는 경우가 있다 — 같은 이름으로 여러 개 올리면
        // 배열이 아니라 단일 파일로 들어와 max 가 세어지지 않는다.
        if (count($request->file('documents') ?? []) > ApplicationDocuments::MAX_FILES) {
            throw ValidationException::withMessages([
                'documents' => [ApplicationDocuments::messages()['documents.max']],
            ]);
        }
    }

    /** 올린 파일을 근로자 서류로 저장한다 (웹·앱 공용). */
    private function storeFiles(Request $request, Worker $worker, ?string $note = null): void
    {
        ApplicationDocuments::store($request->file('documents') ?? [], $worker, $note);
    }
}
