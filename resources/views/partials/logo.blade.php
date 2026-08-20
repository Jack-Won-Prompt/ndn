{{--
    회사 로고 — 사이트·포털·콘솔·메일 링크 화면이 함께 쓴다.

    한 벌이 아니라 두 벌이다. 로고 글자가 검정+금색이라 어두운 면에 그대로 얹으면
    'ND' 가 배경에 묻는다. 그래서 어두운 면에는 검정을 흰색으로 바꾼 판을 쓴다.
    한 군데서만 고르게 모아 둔 이유는, 로고가 바뀔 때 찾아다닐 곳을 하나로 줄이기 위해서다.

    $on     'paper'(밝은 면, 기본) | 'ink'(어두운 면)
    $sub    로고 옆 작은 글씨. null 이면 안 붙인다
    $href   누르면 갈 곳. null 이면 링크가 아닌 그냥 표시
    $label  스크린리더용 이름

    크기는 CSS(.nd-logo__img) 가 정한다 — 좁은 화면에서 함께 줄어야 하는데,
    여기서 inline style 로 높이를 박으면 그 규칙을 이긴다.
--}}
@php
    $on ??= 'paper';
    $sub ??= null;
    $href ??= null;
    $label ??= 'N.D.N Korea';

    $file = 'site/assets/'.($on === 'ink' ? 'logo-light.png' : 'logo.png');

    // 파일이 바뀌면 주소도 바뀌어야 캐시된 옛 로고가 남지 않는다.
    $stamp = @filemtime(public_path($file));

    $tag = $href !== null ? 'a' : 'span';
@endphp

{{-- width/height 는 CSS 기본 높이(30px)와 원본 비율 480:219 로 맞춘 값이다.
     그림이 오기 전에 자리를 잡아 주어 헤더가 덜컹거리지 않는다. --}}
<{{ $tag }} class="nd-logo @if ($on === 'ink') nd-logo--ink @endif"
    @if ($href !== null) href="{{ $href }}" @endif aria-label="{{ $label }}">
    <img class="nd-logo__img" src="{{ asset($file) }}@if ($stamp)?v={{ $stamp }}@endif"
         alt="N.D.N" width="66" height="30">
    @if ($sub !== null)
        <span class="nd-logo__sub">{{ $sub }}</span>
    @endif
</{{ $tag }}>
