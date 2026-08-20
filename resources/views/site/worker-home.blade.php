@extends('site.layout')

@section('title', '내 정보 — N.D.N Korea')

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <h1 class="nd-h1">{{ $worker->name }} 님</h1>
            <p class="nd-lead">근무지와 본인 정보를 확인할 수 있습니다.</p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow">

            @if (session('status'))
                <div class="nd-note nd-note--ok" style="margin-bottom:22px" role="status">{{ session('status') }}</div>
            @endif

            {{-- 근무지 --}}
            <div class="nd-panel">
                <h2 class="nd-panel__title">내 근무지</h2>

                @if ($workplace)
                    <dl class="nd-dl nd-dl--one" style="border-top:0">
                        <div><dt>농가</dt><dd>{{ $workplace['farm'] }}</dd></div>
                        <div><dt>지역</dt><dd>{{ $workplace['city'] ?: '—' }}</dd></div>
                        <div><dt>주소</dt><dd>{{ $workplace['address'] ?: '—' }}</dd></div>
                        <div><dt>품목</dt><dd>{{ $workplace['crop'] ?: '—' }}</dd></div>
                        <div><dt>상태</dt><dd>{{ $workplace['status'] }}</dd></div>
                        <div><dt>근무 기간</dt><dd>{{ $workplace['start_date'] ?: '—' }} ~ {{ $workplace['end_date'] ?: '—' }}</dd></div>
                    </dl>

                    @if ($workplace['arrival'])
                        <h2 class="nd-panel__title" style="margin-top:30px">입국 일정</h2>
                        <dl class="nd-dl nd-dl--one" style="border-top:0">
                            <div><dt>상태</dt><dd>{{ $workplace['arrival']['status'] }}</dd></div>
                            <div><dt>항공편</dt><dd>{{ $workplace['arrival']['flight_no'] ?: '—' }}</dd></div>
                            <div><dt>공항</dt><dd>{{ $workplace['arrival']['airport'] ?: '—' }}</dd></div>
                            <div><dt>예정 시각</dt><dd>{{ $workplace['arrival']['scheduled'] ?: '—' }}</dd></div>
                        </dl>
                    @endif
                @else
                    <p class="nd-help">
                        아직 근무지가 정해지지 않았습니다. 배치가 확정되면 이 화면에 표시됩니다.
                    </p>
                @endif
            </div>

            {{-- 본인 정보 — 여권번호·생년월일은 보여 주지 않는다(§7-1). --}}
            <div class="nd-panel" style="margin-top:22px">
                <h2 class="nd-panel__title">내 정보</h2>
                <dl class="nd-dl nd-dl--one" style="border-top:0">
                    <div><dt>이름</dt><dd>{{ $worker->name }}</dd></div>
                    <div><dt>이메일</dt><dd>{{ $worker->email }}</dd></div>
                    <div><dt>국적</dt><dd>{{ $worker->nationality }}</dd></div>
                    <div><dt>지원 지역</dt><dd>{{ $worker->city?->label() ?: '—' }}</dd></div>
                    <div><dt>상태</dt><dd>{{ $worker->status->label() }}</dd></div>
                </dl>
                <p class="nd-help" style="margin-top:12px">
                    여권번호·생년월일 같은 정보는 안전하게 보관하고 있으며 이 화면에는 표시하지 않습니다.
                    [내 정보 수정]에서 확인하고 바꿀 수 있습니다.
                </p>
                <div class="nd-btnrow" style="margin-top:16px">
                    <a class="nd-btn nd-btn--accent" href="{{ route('worker.profile') }}">내 정보 수정</a>
                </div>
            </div>

            {{-- 제출한 서류 --}}
            <div class="nd-panel" style="margin-top:22px">
                <h2 class="nd-panel__title">내가 낸 서류 ({{ count($files) }}건)</h2>

                @if (count($files))
                    <ul class="nd-filelist">
                        @foreach ($files as $f)
                            <li>
                                <a href="{{ route('worker.files.show', $f['id']) }}">{{ $f['name'] }}</a>
                                <span class="nd-help">{{ $f['size'] }} · {{ $f['uploaded_at'] }}</span>
                                @if ($f['missing'])<span class="nd-err">파일을 찾을 수 없습니다</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="nd-help">아직 낸 서류가 없습니다.</p>
                @endif

                <div class="nd-btnrow" style="margin-top:16px">
                    <a class="nd-btn" href="{{ route('worker.profile') }}">서류 추가하기</a>
                </div>
            </div>

            <form method="POST" action="{{ route('worker.logout') }}" style="margin-top:26px">
                @csrf
                <button class="nd-btn" type="submit">로그아웃</button>
            </form>
        </div>
    </section>
@endsection
