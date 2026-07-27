<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Http\Controllers\Api;

use App\Domains\Onboarding\Actions\GrantConsentAction;
use App\Domains\Onboarding\Actions\RevokeConsentAction;
use App\Domains\Onboarding\Models\ConsentRecord;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use App\Shared\Enums\ConsentPurpose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * 근로자 앱 — 동의 관리 (CLAUDE.md §7-4).
 *
 * 근로자가 본인 동의 상태를 직접 보고 철회할 수 있어야 한다. ConsentRecord 는
 * 목적별로 행을 분리해 철회 시각까지 남기도록 설계돼 있지만, 그동안 근로자가
 * 접근할 경로가 없었다.
 *
 * 철회는 기존 행을 지우지 않고 revoked_at 을 남긴다 — 언제까지 동의가 유효했는지가
 * 증빙이기 때문이다.
 */
class ConsentController extends Controller
{
    /**
     * 목적에 대응하는 기관 유형.
     *
     * ConsentRecord 는 목적·기관별로 행을 나눠 저장하고(§7-4), 대리점 배정
     * (AssignSettlementAction)은 `agency_type='partner_agency'` 로 조회한다.
     * 앱 동의를 기관 유형 없이 저장하면 그 조회에 걸리지 않아, 근로자가 동의해도
     * 배정이 거부되는 상태가 된다.
     */
    private static function agencyTypeFor(ConsentPurpose $purpose): ?string
    {
        return $purpose === ConsentPurpose::ThirdPartyAgency ? 'partner_agency' : null;
    }

    /** 목적별 현재 동의 상태 (전체 목적을 항상 내려준다) */
    public function index(Request $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $active = $worker->consents()
            ->active()
            ->get()
            ->keyBy(fn (ConsentRecord $c) => $c->purpose->value);

        return response()->json([
            'data' => array_map(function (ConsentPurpose $purpose) use ($active) {
                $record = $active->get($purpose->value);

                return [
                    'purpose' => $purpose->value,
                    'label' => $purpose->label(),
                    'granted' => $record !== null,
                    'granted_at' => $record?->granted_at?->toIso8601String(),
                    // 철회하면 해당 서비스가 멈추는지 — 앱이 경고를 띄우는 데 쓴다
                    'required_for_service' => $purpose === ConsentPurpose::SettlementService
                        || $purpose === ConsentPurpose::ThirdPartyAgency,
                ];
            }, ConsentPurpose::cases()),
        ]);
    }

    /** 동의 */
    public function grant(Request $request, GrantConsentAction $action): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', new Enum(ConsentPurpose::class)],
        ]);

        /** @var Worker $worker */
        $worker = $request->user();
        $purpose = ConsentPurpose::from($data['purpose']);

        // 앱에서의 동의는 목적 단위다. 항목(item)은 목적명을 그대로 쓴다.
        $action->execute($worker, $purpose, $purpose->value, self::agencyTypeFor($purpose));

        activity('consent')
            ->performedOn($worker)
            ->withProperties(['purpose' => $purpose->value, 'action' => 'grant'])
            ->log('근로자 동의(앱)');

        return $this->index($request);
    }

    /** 철회 — 해당 목적의 활성 동의를 모두 철회한다 */
    public function revoke(Request $request, RevokeConsentAction $action): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', new Enum(ConsentPurpose::class)],
        ]);

        /** @var Worker $worker */
        $worker = $request->user();
        $purpose = ConsentPurpose::from($data['purpose']);

        $worker->consents()
            ->active()
            ->where('purpose', $purpose->value)
            ->get()
            ->each(fn (ConsentRecord $consent) => $action->execute($consent));

        activity('consent')
            ->performedOn($worker)
            ->withProperties(['purpose' => $purpose->value, 'action' => 'revoke'])
            ->log('근로자 동의 철회(앱)');

        return $this->index($request);
    }
}
