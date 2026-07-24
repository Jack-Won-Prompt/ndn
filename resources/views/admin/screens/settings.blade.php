@extends('admin.screens.layout')
@section('title', '사이트 설정')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">사이트 설정</h1>
            <p class="screen__sub">회사소개 사이트에 표시되는 통계·사업자정보·연락처를 편집합니다. 저장 즉시 사이트에 반영됩니다.</p>
        </div>
    </div>

    @if ($saved)
        <div class="notice" style="background:#DFF6EA;border-color:#B7E4C7;color:#0F5132">✓ 저장되었습니다. 사이트에 반영되었습니다.</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.save') }}">
        @csrf
        @foreach ($groups as $group)
            <div class="mv2-card" style="margin-bottom:16px">
                <div class="mv2-card__head">
                    <span class="mv2-card__title"><span class="mv2-card__title-bar"></span>{{ $group['group'] }}</span>
                </div>
                <div style="padding:18px 20px">
                    <div class="set-grid">
                        @foreach ($group['fields'] as $f)
                            @php $name = str_replace('.', '__', $f['key']); @endphp
                            <div class="set-field">
                                <label for="{{ $name }}">{{ $f['label'] }}</label>
                                <input id="{{ $name }}" name="{{ $name }}"
                                       type="{{ $f['type'] ?? 'text' }}"
                                       value="{{ $values[$f['key']] ?? '' }}"
                                       placeholder="{{ $f['ph'] ?? '' }}">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <div style="display:flex;gap:8px;align-items:center">
            <button type="submit" class="set-save">저장</button>
            <span class="screen__sub" style="margin:0">비워 두면 사이트에는 자리표시자(○○ 등)가 그대로 표시됩니다.</span>
        </div>
    </form>

    <style>
        .set-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 14px 20px; }
        .set-field { display: flex; flex-direction: column; gap: 6px; }
        .set-field label { font-size: 13px; font-weight: 600; color: var(--mv2-text-default); }
        .set-field input {
            padding: 9px 12px; font-family: inherit; font-size: 14px;
            border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-sm);
        }
        .set-field input:focus { outline: none; border-color: var(--mv2-primary-500); box-shadow: 0 0 0 3px rgba(30,156,146,.18); }
        .set-save {
            padding: 10px 22px; font-family: inherit; font-size: 14px; font-weight: 700;
            background: var(--mv2-primary-500); color: #fff; border: 0; border-radius: var(--mv2-r-sm); cursor: pointer;
        }
        .set-save:hover { background: var(--mv2-primary-600); }
        @media (max-width: 720px) { .set-grid { grid-template-columns: 1fr; } }
    </style>
@endsection
