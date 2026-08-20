<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Controllers\Web;

use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Actions\UpdateWorkerProfileAction;
use App\Domains\Recruitment\Enums\WorkerFileType;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use App\Shared\Translation\Concerns\RendersInWorkerLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 합격한 근로자의 웹 화면 (업무흐름 §2).
 *
 * 볼 수 있는 것은 **자기 근무지와 본인 정보뿐**이다. 농가의 다른 근로자, 같은
 * 지역의 배치 현황, 점검 결과 같은 것은 보이지 않는다 — 이 화면은 "내가 어디서
 * 일하는지 확인하는 곳" 이지 조회 도구가 아니다.
 *
 * 읽기 전용이다. 정보 수정은 앱 온보딩·담당자를 거친다.
 */
class WorkerHomeController extends Controller
{
    use RendersInWorkerLocale;

    public function show(): Response
    {
        /** @var Worker $worker */
        $worker = Auth::guard('worker')->user();

        $placement = $worker->placements()
            ->with(['farm.city', 'arrival'])
            ->whereIn('status', [
                PlacementStatus::Confirmed->value,
                PlacementStatus::Proposed->value,
            ])
            ->latest('id')
            ->first();

        // 이 사람이 고른 언어로 보여 준다(§6).
        return $this->renderLocalized('site.worker-home', [
            'worker' => $worker,
            'placement' => $placement,
            'workplace' => $this->workplace($placement),
            // 본인이 올린 서류는 목록만 보여 준다. 무엇을 냈는지 본인은 알아야 한다.
            'files' => $worker->files()->latest('id')->get()->map(fn (WorkerFile $f) => [
                'id' => $f->id,
                'name' => $f->original_name,
                'size' => $f->sizeLabel(),
                'uploaded_at' => LocalTime::format($f->created_at),
                'missing' => ! $f->exists(),
            ])->all(),
        ], $worker);
    }

    /**
     * 근무지 — 농가 이름·주소·품목과 기간.
     *
     * 농가 대표의 연락처는 싣지 않는다. 이 화면은 로그인만 하면 열리고, 농가
     * 개인 연락처는 근로자에게 직접 알려 주는 정보가 아니다.
     *
     * @return array<string, mixed>|null
     */
    private function workplace(?Placement $placement): ?array
    {
        $farm = $placement?->farm;

        if ($farm === null) {
            return null;
        }

        $arrival = $placement->arrival;

        return [
            'farm' => $farm->name,
            'city' => $farm->city?->label(),
            'address' => $farm->address,
            'crop' => $farm->main_crop,
            'status' => $placement->status->label(),
            'start_date' => $placement->start_date?->toDateString(),
            'end_date' => $placement->end_date?->toDateString(),
            'arrival' => $arrival === null ? null : [
                'status' => $arrival->status->label(),
                'flight_no' => $arrival->flight_no,
                'airport' => $arrival->airport,
                'scheduled' => LocalTime::format($arrival->scheduled_arrival_at),
            ],
        ];
    }

    /**
     * 내 정보 수정 화면.
     *
     * 로그인한 본인이므로 여권번호·생년월일·전화를 **되돌려 보여 준다.** 보완
     * 링크(로그인 없음)와 다른 점이다 — 그쪽은 비워 두고 새로 적게 한다(§7-1).
     */
    public function edit(): Response
    {
        /** @var Worker $worker */
        $worker = Auth::guard('worker')->user();

        return $this->renderLocalized('site.worker-edit', [
            'worker' => $worker,
            'prefill' => [
                'name' => $worker->name,
                'nationality' => $worker->nationality,
                'locale' => $worker->locale,
                'passport_no' => $worker->passport_no,
                'birth_date' => $worker->birth_date,
                'phone_home_country' => $worker->phone_home_country,
            ],
            'maxFiles' => WorkerApplyController::MAX_FILES,
            'maxKb' => WorkerFile::MAX_KB,
            'mimes' => WorkerFile::MIMES,
        ], $worker);
    }

    /** 내 정보 저장 + 서류 추가. */
    public function update(Request $request, UpdateWorkerProfileAction $profile): RedirectResponse
    {
        /** @var Worker $worker */
        $worker = Auth::guard('worker')->user();

        $data = $request->validate(UpdateWorkerProfileAction::rules() + [
            'documents' => ['nullable', 'array', 'max:'.WorkerApplyController::MAX_FILES],
            'documents.*' => ['file', 'mimes:'.WorkerFile::MIMES, 'max:'.WorkerFile::MAX_KB],
        ]);

        $profile->execute($worker, $data, 'worker-web');

        // 서류는 지우지 않고 더한다. 이미 낸 것을 본인이 지우면 심사 근거가 사라진다.
        foreach ($request->file('documents') ?? [] as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $path = $file->storeAs(
                WorkerFile::DIR.'/'.$worker->id,
                'self_'.Str::random(16).'.'.$ext,
                ['disk' => WorkerFile::DISK],
            );

            WorkerFile::create([
                'worker_id' => $worker->id,
                'type' => WorkerFileType::Other,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => Storage::disk(WorkerFile::DISK)->size($path),
                'mime' => $file->getClientMimeType(),
                'uploaded_by' => null,
            ]);
        }

        return redirect()->route('worker.home')->with('status', '저장했습니다.');
    }

    /** 본인이 올린 서류 내려받기 — 자기 것만. */
    public function file(WorkerFile $file): StreamedResponse
    {
        /** @var Worker $worker */
        $worker = Auth::guard('worker')->user();

        // 남의 서류 번호를 넣어도 열리지 않아야 한다.
        abort_unless($file->worker_id === $worker->id, 404);
        abort_unless($file->exists(), 404);

        return Storage::disk(WorkerFile::DISK)
            ->response($file->path, $file->original_name);
    }
}
