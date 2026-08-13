<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Recruitment\Enums\WorkerFileType;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 근로자 개인 서류 (콘솔).
 *
 * 본사가 현지 인력을 직접 가입시킬 때 여권 사본·건강검진 결과 같은 개인 서류를
 * 함께 보관한다. 전원 공통 서식(필수 동의 문서)과는 다른 것이다.
 *
 * 서류 자체가 민감정보라 private 저장 + 인증 라우트로만 나가고, 열면 열람
 * 기록을 남긴다(§7-6).
 */
class WorkerFileController extends Controller
{
    /**
     * 한 근로자의 서류 목록 (화면 표시용).
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(Worker $worker): array
    {
        return WorkerFile::query()
            ->where('worker_id', $worker->id)
            ->with('uploader:id,name')
            ->latest('id')
            ->get()
            ->map(fn (WorkerFile $f) => [
                'id' => $f->id,
                'type' => $f->type->value,
                'type_label' => $f->type->label(),
                'name' => $f->original_name,
                'size' => $f->sizeLabel(),
                'expires_on' => $f->expires_on?->format('Y-m-d'),
                'expired' => $f->isExpired(),
                'expiring' => $f->expiresSoon(),
                'note' => $f->note,
                'uploaded_by' => $f->uploader?->name ?? '—',
                'uploaded_at' => LocalTime::format($f->created_at),
                'url' => route('admin.workers.files.show', [$worker, $f]),
                // 파일이 사라졌는데 목록에만 남아 있으면 있는 줄 안다.
                'missing' => ! $f->exists(),
            ])
            ->all();
    }

    /** 서류 올리기. */
    public function store(Request $request, Worker $worker): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(WorkerFileType::class)],
            'file' => ['required', 'file', 'max:'.WorkerFile::MAX_KB, 'mimes:'.WorkerFile::MIMES],
            'expires_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $upload = $request->file('file');
        $ext = strtolower($upload->getClientOriginalExtension());

        // 저장 이름은 ASCII 로 짓는다 — 원본 파일명이 한글이면 서버·백업에서 깨진다.
        // 화면에는 original_name 을 보여 주므로 사용자는 차이를 느끼지 않는다.
        $name = $data['type'].'_'.Str::random(10).'.'.$ext;
        $path = WorkerFile::DIR.'/'.$worker->id.'/'.$name;

        Storage::disk(WorkerFile::DISK)->putFileAs(
            WorkerFile::DIR.'/'.$worker->id, $upload, $name,
        );

        $file = WorkerFile::create([
            'worker_id' => $worker->id,
            'type' => $data['type'],
            'path' => $path,
            'original_name' => mb_substr($upload->getClientOriginalName(), 0, 255),
            'size' => (int) $upload->getSize(),
            'mime' => $upload->getMimeType(),
            'expires_on' => $data['expires_on'] ?? null,
            'note' => $data['note'] ?? null,
            'uploaded_by' => Auth::id(),
        ]);

        activity('worker-file')
            ->performedOn($file)
            ->causedBy(Auth::user())
            ->withProperties(['worker_id' => $worker->id, 'type' => $file->type->value])
            ->log('근로자 서류 올림');

        return response()->json([
            'ok' => true,
            'message' => $file->type->label().'을(를) 올렸습니다.',
            'rows' => self::rows($worker),
        ]);
    }

    /**
     * 서류 내려받기 (private 저장 · ndn_admin 전용).
     *
     * 여권 사본은 그 자체로 민감정보다. 여는 것도 개인정보 열람이다(§7-6).
     */
    public function show(Worker $worker, WorkerFile $file): StreamedResponse
    {
        abort_unless($file->worker_id === $worker->id, 404);
        abort_unless($file->exists(), 404, '파일이 없습니다.');

        $worker->recordAccessBy(Auth::user(), 'worker-file:'.$file->type->value);

        return Storage::disk(WorkerFile::DISK)->download($file->path, $file->original_name);
    }

    /** 서류 지우기. */
    public function destroy(Worker $worker, WorkerFile $file): JsonResponse
    {
        abort_unless($file->worker_id === $worker->id, 404);

        // 파일도 함께 지운다. 공통 서식과 달리 이건 '무엇에 동의했는지' 의 증빙이
        // 아니라 보관 자료라, 남겨 두면 파기 요청(§7-7) 때 빠뜨리기 쉽다.
        Storage::disk(WorkerFile::DISK)->delete($file->path);

        activity('worker-file')
            ->causedBy(Auth::user())
            ->withProperties([
                'worker_id' => $worker->id,
                'type' => $file->type->value,
                'name' => $file->original_name,
            ])
            ->log('근로자 서류 지움');

        $file->delete();

        return response()->json(['ok' => true, 'message' => '서류를 지웠습니다.', 'rows' => self::rows($worker)]);
    }
}
