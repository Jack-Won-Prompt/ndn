@extends('site.layout')

@section('title', '서류 보완 — N.D.N Korea')

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <h1 class="nd-h1">서류 보완</h1>
            <p class="nd-lead">
                신청은 그대로 있습니다. 아래에 표시된 것만 채워서 다시 보내 주세요.
            </p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow">
            @if ($errors->any())
                <div class="nd-note nd-note--err" style="margin-bottom:22px" role="alert">
                    입력한 내용을 다시 확인해 주세요.
                </div>
            @endif

            <div class="nd-note nd-note--warn" style="margin-bottom:22px">
                <b>담당자가 요청한 항목 {{ count($items) }}건</b>
                {{-- 이 글자들은 이미 이 사람의 언어다(worker.doc_* 번역 키).
                     번역기에 한 번 더 넣으면 '생년월일' 이 'Sinh ngay' 처럼 깨진다. --}}
                <ul class="nd-doclist" style="margin-top:8px" data-no-translate>
                    @forelse ($items as $item)<li>{{ $item }}</li>@empty<li>담당자 안내를 확인해 주세요.</li>@endforelse
                </ul>
                @if (filled($note))
                    <p style="margin-top:10px">{{ $note }}</p>
                @endif
            </div>

            <form class="nd-panel" method="POST" action="{{ $action }}"
                  enctype="multipart/form-data">
                @csrf

                <h2 class="nd-panel__title">서류 올리기</h2>
                <p class="nd-help" style="margin-bottom:12px">
                    보통 요청되는 서류:
                    <span data-no-translate>{{ implode(' · ', $expected) }}</span>
                </p>

                <div class="nd-field">
                    <label for="sp-docs">파일 선택 (여러 개 가능)</label>
                    <input class="nd-input" id="sp-docs" type="file"
                           name="documents[]" multiple accept=".{{ str_replace(',', ',.', $mimes) }}">
                    <p class="nd-help">
                        최대 {{ $maxFiles }}개 · 하나당 {{ round($maxKb / 1024) }}MB 이하 ·
                        {{ strtoupper(str_replace(',', ', ', $mimes)) }}
                    </p>
                    @error('documents')<p class="nd-err">{{ $message }}</p>@enderror
                    @error('documents.*')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <h2 class="nd-panel__title" style="margin-top:34px">정보 수정 (바꿀 것만)</h2>
                <p class="nd-help" style="margin-bottom:12px">
                    <b>비워 두면 지금 값이 그대로 유지됩니다.</b> 바꾸고 싶은 칸만 채우세요.
                </p>

                @include('site._apply-fields', ['mode' => 'supplement'])

                <div class="nd-field">
                    <label for="sp-note">담당자에게 남길 말</label>
                    <textarea class="nd-input" id="sp-note" name="note" rows="3" maxlength="1000">{{ old('note') }}</textarea>
                </div>

                <div class="nd-btnrow" style="margin-top:22px">
                    <button class="nd-btn nd-btn--accent" type="submit">보완해서 제출하기</button>
                </div>
            </form>
        </div>
    </section>
@endsection
