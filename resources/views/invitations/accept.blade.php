<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>계정 설정 — N.D.N Korea 협력 포털</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
    *{box-sizing:border-box;}
    body{margin:0;font-family:"Pretendard Variable","Pretendard",-apple-system,"Malgun Gothic",sans-serif;background:#F4F6F8;color:#1B1E24;display:flex;min-height:100vh;align-items:center;justify-content:center;-webkit-font-smoothing:antialiased;}
    .card{width:420px;max-width:92%;background:#fff;border:1px solid #E3E6EA;border-radius:16px;padding:32px 30px;box-shadow:0 10px 40px rgba(15,23,42,.08);}
    .brand{font-size:20px;font-weight:800;letter-spacing:.04em;}
    .brand span{font-size:12px;color:#6B7280;margin-left:8px;letter-spacing:.1em;text-transform:uppercase;}
    h1{font-size:19px;margin:20px 0 4px;}
    .sub{font-size:13px;color:#6B7280;margin-bottom:20px;}
    .role{display:inline-block;font-size:12px;font-weight:700;color:#1E9C92;background:#E9F6F4;border-radius:100px;padding:3px 11px;margin-bottom:16px;}
    label{display:block;font-size:12px;font-weight:700;color:#4B5563;margin:12px 0 5px;}
    input{width:100%;font-family:inherit;font-size:14px;padding:10px 12px;border:1px solid #D5D9DF;border-radius:8px;}
    input:focus{outline:none;border-color:#1E9C92;box-shadow:0 0 0 3px rgba(30,156,146,.15);}
    input[readonly]{background:#F4F6F8;color:#6B7280;}
    .btn{width:100%;margin-top:20px;font-family:inherit;font-size:15px;font-weight:700;background:#1E9C92;color:#fff;border:0;border-radius:8px;padding:12px;cursor:pointer;}
    .btn:hover{background:#178578;}
    .err{background:#FDECEC;color:#B42318;font-size:13px;border-radius:8px;padding:10px 12px;margin-bottom:14px;}
    .err ul{margin:0;padding-left:18px;}
</style>
</head>
<body>
    <div class="card">
        <div class="brand"><b>N.D.N</b><span>협력 포털</span></div>
        <h1>계정 설정</h1>
        <p class="sub">초대를 수락하고 로그인 비밀번호를 설정하세요.</p>
        <div class="role">{{ $roleLabel }}</div>

        @if ($errors->any())
            <div class="err">
                <ul>
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('invite.accept', ['token' => $token]) }}">
            @csrf
            <label>이메일</label>
            <input type="email" value="{{ $email }}" readonly>

            <label>이름</label>
            <input type="text" name="name" value="{{ old('name', $name) }}" placeholder="담당자 이름" required autofocus>

            <label>비밀번호 (8자 이상)</label>
            <input type="password" name="password" placeholder="새 비밀번호" required>

            <label>비밀번호 확인</label>
            <input type="password" name="password_confirmation" placeholder="비밀번호 확인" required>

            <button type="submit" class="btn">계정 만들기</button>
        </form>
    </div>
</body>
</html>
