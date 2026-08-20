<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Recruitment\Enums\Nationality;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Actions\SendNoticeAction;
use App\Domains\Support\Models\Notice;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Notifications\Models\DeviceToken;
use App\Shared\Support\LocalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 공지사항 발송·이력 (관리자 콘솔).
 *
 * 대상은 다섯 가지 — 전체(근로자+담당자 앱) / 근로자 전체 / 국적별 / 상태별 /
 * 근로자 선택. 발송 판단은 SendNoticeAction 이 한다.
 */
class NoticeController extends Controller
{
    /**
     * 발송 이력(최근순).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(): array
    {
        return Notice::query()
            ->withCount('recipients')
            ->with('recipients:id,name')
            ->latest('id')->limit(200)->get()
            ->map(fn (Notice $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'target' => $n->targetLabel(),
                'count' => $n->recipients_count,
                // 골라 보낸 공지만 이름이 남는다. 나머지는 건수로 충분하다.
                'who' => $n->target === Notice::TARGET_SELECTED
                    ? $n->recipients->pluck('name')->implode(', ')
                    : '',
                'sent' => LocalTime::format($n->created_at),
            ])->all();
    }

    /** 대상 선택지 */
    public static function targetOptions(): array
    {
        return Notice::targetOptions();
    }

    /** @return array<string, string> */
    public static function nationalityOptions(): array
    {
        return Nationality::adminOptions();
    }

    /**
     * 상태별 선택지 — 공지를 보낼 만한 상태만.
     *
     * 가입 거절·이탈한 사람에게 공지를 보낼 이유가 없다. 목록에 두면 실수로 눌린다.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect([WorkerStatus::Active, WorkerStatus::Inactive, WorkerStatus::Returned])
            ->mapWithKeys(fn (WorkerStatus $s) => [$s->value => $s->label()])
            ->all();
    }

    /**
     * 골라 보낼 근로자 목록.
     *
     * 기기 등록 여부를 함께 준다 — 앱을 안 깐 사람을 골라 놓고 왜 못 받았는지
     * 나중에 찾는 일이 없도록 화면에서 미리 드러낸다.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function workerOptions(): array
    {
        $withDevice = DeviceToken::query()
            ->where('tokenable_type', (new Worker)->getMorphClass())
            ->distinct()
            ->pluck('tokenable_id')
            ->flip();

        return Worker::query()
            ->where('status', WorkerStatus::Active->value)
            ->orderBy('name')
            ->limit(2000)
            ->get(['id', 'name', 'nationality', 'locale'])
            ->map(fn (Worker $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'nationality' => $w->nationality,
                'locale' => $w->locale,
                'app' => $withDevice->has($w->id),
            ])->all();
    }

    /** 앱 기기를 등록한 담당자 수 — '전체' 를 고를 때 몇 명이 더 받는지 보여 준다. */
    public static function appUserCount(): int
    {
        return DeviceToken::query()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->distinct()
            ->count('tokenable_id');
    }

    /** 공지 발송 */
    public function store(Request $request, SendNoticeAction $action): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'target' => ['required', Rule::in(array_keys(Notice::targetOptions()))],
            'target_value' => [
                'nullable', 'string', 'max:20',
                Rule::requiredIf(fn () => in_array(
                    $request->input('target'),
                    [Notice::TARGET_NATIONALITY, Notice::TARGET_STATUS],
                    true,
                )),
            ],
            'worker_ids' => [
                'array',
                Rule::requiredIf(fn () => $request->input('target') === Notice::TARGET_SELECTED),
            ],
            'worker_ids.*' => ['integer', 'exists:workers,id'],
        ], [
            'worker_ids.required' => '보낼 근로자를 한 명 이상 고르세요.',
            'target_value.required' => '대상 값을 고르세요.',
        ]);

        $needsValue = in_array($data['target'], [Notice::TARGET_NATIONALITY, Notice::TARGET_STATUS], true);

        $notice = $action->execute(
            $data['title'],
            $data['body'],
            $data['target'],
            $needsValue ? ($data['target_value'] ?? null) : null,
            Auth::id(),
            array_map('intval', $data['worker_ids'] ?? []),
        );

        return redirect(url('admin/screen/notices'))
            ->with('notice_sent', $notice->recipients_count);
    }
}
