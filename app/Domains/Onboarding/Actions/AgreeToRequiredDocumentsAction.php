<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Actions;

use App\Domains\Onboarding\Models\DocumentConsent;
use App\Domains\Onboarding\Models\RequiredDocument;
use App\Domains\Recruitment\Models\Worker;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 필수 문서 동의 기록 (업무흐름 §3, CLAUDE.md §7-4).
 *
 * 근로자가 화면에서 체크한 문서들을 현재 버전으로 기록한다.
 * 이미 같은 버전에 동의한 문서는 조용히 건너뛴다(재전송·중복 탭 대비).
 */
class AgreeToRequiredDocumentsAction
{
    /**
     * @param  list<int>  $documentIds
     * @return int 새로 기록된 동의 수
     */
    public function execute(Worker $worker, array $documentIds): int
    {
        $documents = RequiredDocument::active()->whereIn('id', $documentIds)->get();

        if ($documents->count() !== count(array_unique($documentIds))) {
            throw new RuntimeException('사용 중이 아닌 문서가 포함되어 있습니다.');
        }

        return DB::transaction(function () use ($worker, $documents) {
            $created = 0;

            foreach ($documents as $doc) {
                $consent = DocumentConsent::firstOrCreate(
                    [
                        'worker_id' => $worker->id,
                        'required_document_id' => $doc->id,
                        'version' => $doc->version,
                    ],
                    ['agreed_at' => now()],
                );

                if ($consent->wasRecentlyCreated) {
                    $created++;
                }
            }

            return $created;
        });
    }
}
