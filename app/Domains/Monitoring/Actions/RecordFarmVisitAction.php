<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Actions;

use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Enums\FarmVisitStatus;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * 본사 월별 농가 방문 점검 등록 (CLAUDE.md §4).
 *
 * 방문 기록 생성과 현장 사진(private 저장) 업로드를 한 트랜잭션으로 처리한다.
 * 위치정보는 저장하지 않는다(§7-2).
 */
class RecordFarmVisitAction
{
    private const DISK = 'local';

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile|null>  $photos
     */
    public function execute(Farm $farm, User $inspector, array $data, array $photos = []): FarmVisit
    {
        return DB::transaction(function () use ($farm, $inspector, $data, $photos) {
            $visit = FarmVisit::create([
                'farm_id' => $farm->id,
                'visited_by' => $inspector->id,
                'visited_on' => $data['visited_on'],
                'farm_status' => $data['farm_status'] ?? FarmVisitStatus::Normal->value,
                'worker_status' => $data['worker_status'] ?? FarmVisitStatus::Normal->value,
                'worker_headcount' => $data['worker_headcount'] ?? null,
                'work_note' => $data['work_note'] ?? null,
                'issue_note' => $data['issue_note'] ?? null,
                'action_note' => $data['action_note'] ?? null,
                'memo' => $data['memo'] ?? null,
            ]);

            foreach (array_filter($photos) as $file) {
                $path = $file->store("farm-visits/{$visit->id}", self::DISK);
                $visit->photos()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'created_at' => now(),
                ]);
            }

            return $visit;
        });
    }
}
