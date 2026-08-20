@extends('site.layout')

@section('title', '내 정보 수정 — N.D.N Korea')

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('worker.home') }}">내 정보</a><span>›</span>수정</p>
            <h1 class="nd-h1">내 정보 수정</h1>
            <p class="nd-lead">바꿀 것만 채우면 됩니다. 비워 두면 지금 값이 그대로 유지됩니다.</p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow">
            @if ($errors->any())
                <div class="nd-note nd-note--err" style="margin-bottom:22px" role="alert">
                    입력한 내용을 다시 확인해 주세요.
                </div>
            @endif

            <form class="nd-panel" method="POST" action="{{ route('worker.profile.update') }}"
                  enctype="multipart/form-data">
                @csrf

                <h2 class="nd-panel__title">본인 정보</h2>

                @include('site._apply-fields', ['mode' => 'profile'])

                <h2 class="nd-panel__title" style="margin-top:34px">서류 추가</h2>
                <p class="nd-help" style="margin-bottom:12px">
                    새로 낼 서류가 있으면 올려 주세요. <b>이미 낸 서류는 그대로 남습니다</b> —
                    잘못 올린 것이 있으면 담당자에게 알려 주세요.
                </p>

                <div class="nd-field">
                    <label for="wp-docs">파일 선택 (여러 개 가능)</label>
                    <input class="nd-input" id="wp-docs" type="file"
                           name="documents[]" multiple accept=".{{ str_replace(',', ',.', $mimes) }}">
                    <p class="nd-help">
                        최대 {{ $maxFiles }}개 · 하나당 {{ round($maxKb / 1024) }}MB 이하 ·
                        {{ strtoupper(str_replace(',', ', ', $mimes)) }}
                    </p>
                    @error('documents')<p class="nd-err">{{ $message }}</p>@enderror
                    @error('documents.*')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <div class="nd-btnrow" style="margin-top:22px">
                    <button class="nd-btn nd-btn--accent" type="submit">저장하기</button>
                    <a class="nd-btn" href="{{ route('worker.home') }}">취소</a>
                </div>
            </form>
        </div>
    </section>
@endsection
