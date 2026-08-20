@extends('site.layout')

@section('title', '신청이 접수되었습니다 — N.D.N Korea')

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>지원하기</p>
            <h1 class="nd-h1">{{ session('supplemented') ? '보완 서류가 접수되었습니다' : '신청이 접수되었습니다' }}</h1>
            <p class="nd-lead">
                담당자가 확인한 뒤 결과를 알려 드립니다. 서류가 더 필요하면 신청하신 이메일로 요청드립니다.
            </p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow nd-prose">
            <h2>다음 단계</h2>
            <ol>
                <li><b>서류 확인</b> — 담당자가 제출 내용을 확인합니다. 부족한 것이 있으면 이메일로 요청드립니다.</li>
                <li><b>선발</b> — 확인이 끝나면 결과를 알려 드립니다.</li>
                <li><b>합격</b> — 합격하면 곧바로 로그인할 수 있고, 근무지가 정해지면 이 화면에서 확인할 수 있습니다.</li>
            </ol>

            <div class="nd-note" style="margin-top:22px">
                합격 전까지는 로그인할 수 없습니다. 결과 안내를 놓치지 않도록 메일함을 확인해 주세요.
            </div>

            <div class="nd-btnrow" style="margin-top:26px">
                <a class="nd-btn nd-btn--accent" href="{{ route('site.home') }}">홈으로</a>
                <a class="nd-btn" href="{{ route('site.contact') }}">문의하기</a>
            </div>
        </div>
    </section>
@endsection
