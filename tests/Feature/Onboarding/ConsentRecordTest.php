<?php

declare(strict_types=1);

use App\Domains\Onboarding\Actions\GrantConsentAction;
use App\Domains\Onboarding\Actions\RevokeConsentAction;
use App\Domains\Onboarding\Models\ConsentRecord;
use App\Domains\Recruitment\Models\Worker;
use App\Shared\Enums\ConsentPurpose;

/**
 * 동의 이력 (CLAUDE.md §7-4).
 */
it('동의를 부여하면 활성 동의가 생긴다', function () {
    $worker = Worker::factory()->create();

    app(GrantConsentAction::class)->execute(
        $worker,
        ConsentPurpose::ThirdPartyAgency,
        'phone',
        agencyType: 'partner_agency',
        agencyId: 10,
    );

    expect($worker->hasActiveConsent(ConsentPurpose::ThirdPartyAgency, 'partner_agency'))->toBeTrue();
});

it('같은 조합의 동의를 두 번 부여해도 행이 하나만 생긴다', function () {
    $worker = Worker::factory()->create();
    $grant = app(GrantConsentAction::class);

    $a = $grant->execute($worker, ConsentPurpose::SettlementService, 'bank_account');
    $b = $grant->execute($worker, ConsentPurpose::SettlementService, 'bank_account');

    expect($a->id)->toBe($b->id)
        ->and(ConsentRecord::where('worker_id', $worker->id)->count())->toBe(1);
});

it('철회는 행을 지우지 않고 revoked_at 을 채운다 (이력 보존)', function () {
    $worker = Worker::factory()->create();
    $consent = app(GrantConsentAction::class)->execute(
        $worker,
        ConsentPurpose::ThirdPartyAgency,
        'passport_no',
        agencyType: 'partner_agency',
    );

    app(RevokeConsentAction::class)->execute($consent);

    // 행은 남아있고 revoked_at 이 채워짐
    expect(ConsentRecord::where('worker_id', $worker->id)->count())->toBe(1)
        ->and($consent->fresh()->revoked_at)->not->toBeNull()
        ->and($consent->fresh()->isActive())->toBeFalse();

    // 철회 후 활성 동의는 없다
    expect($worker->hasActiveConsent(ConsentPurpose::ThirdPartyAgency, 'partner_agency'))->toBeFalse();
});

it('목적·기관·항목별로 동의가 독립적이다', function () {
    $worker = Worker::factory()->create();
    $grant = app(GrantConsentAction::class);

    $grant->execute($worker, ConsentPurpose::ThirdPartyAgency, 'phone', agencyType: 'partner_agency');
    $grant->execute($worker, ConsentPurpose::Notification, 'phone');

    // 대리점 제공은 동의됨, 하지만 다른 기관 유형은 아님
    expect($worker->hasActiveConsent(ConsentPurpose::ThirdPartyAgency, 'partner_agency'))->toBeTrue()
        ->and($worker->hasActiveConsent(ConsentPurpose::ThirdPartyAgency, 'sending_agency'))->toBeFalse();
});
