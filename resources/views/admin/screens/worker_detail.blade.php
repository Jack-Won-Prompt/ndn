@extends('admin.screens.layout')
@section('title', '근로자 #'.$worker->id)

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">근로자 #{{ $worker->id }}</h1>
            <p class="screen__sub">{{ $worker->name }}</p>
        </div>
    </div>

    <div class="notice">
        이 화면 열람은 개인정보 접근으로 감사 로그(activitylog)에 기록되었습니다 (CLAUDE.md §7-6).
        민감 필드(여권번호·생년월일·전화번호)는 콘솔에 표시하지 않습니다.
    </div>

    <dl class="detail">
        <dt>이름</dt><dd>{{ $worker->name }}</dd>
        <dt>국적</dt><dd>{{ $worker->nationality }}</dd>
        <dt>언어</dt><dd>{{ $worker->locale }}</dd>
        <dt>상태</dt><dd>{{ $worker->status }}</dd>
        <dt>등록일</dt><dd>{{ $worker->created_at?->format('Y-m-d H:i') }}</dd>
    </dl>
@endsection
