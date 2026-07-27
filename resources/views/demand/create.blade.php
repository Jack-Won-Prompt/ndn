@extends('demand.layout')
@section('title', '새 수요 신청')

@section('content')
    <div class="dp-head">
        <h1>새 수요 신청</h1>
        <a href="{{ route('demand.index') }}" class="dp-btn dp-btn--ghost">목록</a>
    </div>

    @if ($farms->isEmpty())
        <div class="dp-card" style="padding:40px;text-align:center;color:#6B7280">
            연결된 농가가 없습니다. NDN 관리자에게 농가 등록을 요청하세요.
        </div>
    @else
    <div class="dp-card" style="padding:24px">
        <form id="dq-form" method="POST"
              data-base="{{ url('farms') }}"
              action="{{ url('farms/'.$farms->first()->id.'/demand') }}">
            @csrf

            <div class="dq-grid">
                @if ($farms->count() > 1)
                    <div class="dq-field dq-field--full">
                        <label>농가</label>
                        <select id="dq-farm" name="_farm">
                            @foreach ($farms as $f)
                                <option value="{{ $f->id }}">{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" id="dq-farm" value="{{ $farms->first()->id }}">
                @endif

                <div class="dq-field">
                    <label>국적 <em>*</em></label>
                    <select name="nationality" required>
                        <option value="VN" @selected(old('nationality')==='VN')>베트남 (VN)</option>
                        <option value="BD" @selected(old('nationality')==='BD')>방글라데시 (BD)</option>
                        <option value="LA" @selected(old('nationality')==='LA')>라오스 (LA)</option>
                        <option value="LK" @selected(old('nationality')==='LK')>스리랑카 (LK)</option>
                    </select>
                </div>

                <div class="dq-field">
                    <label>인원 <em>*</em></label>
                    <input type="number" name="headcount" min="1" max="999" value="{{ old('headcount') }}" required>
                </div>

                <div class="dq-field">
                    <label>성별 <em>*</em></label>
                    <select name="gender" required>
                        @foreach ($genders as $g)
                            <option value="{{ $g->value }}" @selected(old('gender')===$g->value)>{{ $g->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="dq-field">
                    <label>형제·가족 동반</label>
                    <label class="dq-check"><input type="hidden" name="allow_siblings" value="0"><input type="checkbox" name="allow_siblings" value="1" @checked(old('allow_siblings'))> 허용</label>
                </div>

                <div class="dq-field">
                    <label>연령대 (최소)</label>
                    <input type="number" name="age_min" min="18" max="70" value="{{ old('age_min') }}" placeholder="예: 20">
                </div>

                <div class="dq-field">
                    <label>연령대 (최대)</label>
                    <input type="number" name="age_max" min="18" max="70" value="{{ old('age_max') }}" placeholder="예: 45">
                </div>

                <div class="dq-field">
                    <label>품목 <em>*</em></label>
                    <input type="text" name="crop" maxlength="100" value="{{ old('crop') }}" placeholder="예: 고추, 사과" required>
                </div>

                <div class="dq-field">
                    <label>관할 시·군 (선택)</label>
                    <select name="city_id">
                        <option value="">농가 기준 자동</option>
                        @foreach ($cities as $c)
                            <option value="{{ $c->id }}" @selected((int)old('city_id')===$c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="dq-field">
                    <label>근무 시작 <em>*</em></label>
                    <input type="date" name="period_start" value="{{ old('period_start') }}" required>
                </div>

                <div class="dq-field">
                    <label>근무 종료 <em>*</em></label>
                    <input type="date" name="period_end" value="{{ old('period_end') }}" required>
                </div>

                <div class="dq-field dq-field--full">
                    <label>메모</label>
                    <textarea name="note" maxlength="2000" rows="3" placeholder="추가 요청사항">{{ old('note') }}</textarea>
                </div>
            </div>

            <div class="dq-actions">
                <a href="{{ route('demand.index') }}" class="dp-btn dp-btn--ghost">취소</a>
                <button type="submit" class="dp-btn">수요 신청 저장</button>
            </div>
            <p class="dq-hint">저장 시 <b>작성 중(draft)</b> 상태로 생성됩니다. 상세 화면에서 <b>제출</b>하면 시청 취합 대상이 됩니다.</p>
        </form>
    </div>
    @endif

    <style>
        .dq-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 20px;}
        .dq-field{display:flex;flex-direction:column;gap:6px;}
        .dq-field--full{grid-column:1 / -1;}
        .dq-field label{font-size:13px;font-weight:700;color:#4B5563;}
        .dq-field label em{color:#B42318;font-style:normal;}
        .dq-field input,.dq-field select,.dq-field textarea{font-family:inherit;font-size:14px;padding:9px 11px;border:1px solid #D5D9DF;border-radius:8px;background:#fff;}
        .dq-field input:focus,.dq-field select:focus,.dq-field textarea:focus{outline:none;border-color:#1E9C92;box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        .dq-check{flex-direction:row;align-items:center;gap:8px;font-weight:500;color:#333A44;font-size:14px;}
        .dq-check input{width:auto;}
        .dq-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px;}
        .dq-hint{font-size:12px;color:#6B7280;text-align:right;margin:10px 0 0;}
        @media (max-width:640px){.dq-grid{grid-template-columns:1fr;}}
    </style>
    <script>
        (function () {
            var form = document.getElementById('dq-form');
            var farm = document.getElementById('dq-farm');
            if (!form || !farm || farm.tagName !== 'SELECT') return;
            function sync() { form.setAttribute('action', form.dataset.base + '/' + farm.value + '/demand'); }
            farm.addEventListener('change', sync); sync();
        })();
    </script>
@endsection
