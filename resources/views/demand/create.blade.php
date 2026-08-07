@extends('portal.layout', ['active' => 'demand'])
@section('title', '새 수요 신청')

@section('body')
    <div class="nd-pagehead nd-pagehead--row">
        <div>
            <h1>새 수요 신청</h1>
            <p>저장하면 <b>작성 중</b>으로 만들어집니다. 상세 화면에서 제출해야 시청 취합 대상이 됩니다.</p>
        </div>
        <a href="{{ route('demand.index') }}" class="nd-btn nd-btn--line nd-btn--sm">목록</a>
    </div>

    @if ($farms->isEmpty())
        <div class="nd-tablewrap nd-tablewrap--dense">
            <p class="nd-empty">연결된 농가가 없습니다. NDN 관리자에게 농가 등록을 요청하세요.</p>
        </div>
    @else
        <div class="nd-panel">
            <form id="dq-form" method="POST"
                  data-base="{{ url('farms') }}"
                  action="{{ url('farms/'.$farms->first()->id.'/demand') }}">
                @csrf

                @if ($farms->count() > 1)
                    <div class="nd-field">
                        <label for="dq-farm">농가</label>
                        <select class="nd-select" id="dq-farm" name="_farm">
                            @foreach ($farms as $f)
                                <option value="{{ $f->id }}">{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" id="dq-farm" value="{{ $farms->first()->id }}">
                @endif

                <div class="nd-fieldrow">
                    <div class="nd-field">
                        <label for="dq-nat">국적 <em>*</em></label>
                        <select class="nd-select" id="dq-nat" name="nationality" required>
                            <option value="VN" @selected(old('nationality')==='VN')>베트남 (VN)</option>
                            <option value="BD" @selected(old('nationality')==='BD')>방글라데시 (BD)</option>
                            <option value="LA" @selected(old('nationality')==='LA')>라오스 (LA)</option>
                            <option value="LK" @selected(old('nationality')==='LK')>스리랑카 (LK)</option>
                        </select>
                    </div>

                    <div class="nd-field">
                        <label for="dq-head">인원 <em>*</em></label>
                        <input class="nd-input" id="dq-head" type="number" name="headcount"
                               min="1" max="999" value="{{ old('headcount') }}" required>
                    </div>

                    <div class="nd-field">
                        <label for="dq-gender">성별 <em>*</em></label>
                        <select class="nd-select" id="dq-gender" name="gender" required>
                            @foreach ($genders as $g)
                                <option value="{{ $g->value }}" @selected(old('gender')===$g->value)>{{ $g->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="nd-field">
                        <label>형제·가족 동반</label>
                        <label class="nd-check">
                            <input type="hidden" name="allow_siblings" value="0">
                            <input type="checkbox" name="allow_siblings" value="1" @checked(old('allow_siblings'))> 허용
                        </label>
                    </div>

                    <div class="nd-field">
                        <label for="dq-agemin">연령대 (최소)</label>
                        <input class="nd-input" id="dq-agemin" type="number" name="age_min"
                               min="18" max="70" value="{{ old('age_min') }}" placeholder="예: 20">
                    </div>

                    <div class="nd-field">
                        <label for="dq-agemax">연령대 (최대)</label>
                        <input class="nd-input" id="dq-agemax" type="number" name="age_max"
                               min="18" max="70" value="{{ old('age_max') }}" placeholder="예: 45">
                    </div>

                    <div class="nd-field">
                        <label for="dq-crop">품목 <em>*</em></label>
                        <input class="nd-input" id="dq-crop" type="text" name="crop" maxlength="100"
                               value="{{ old('crop') }}" placeholder="예: 고추, 사과" required>
                    </div>

                    <div class="nd-field">
                        <label for="dq-city">관할 시·군 (선택)</label>
                        <select class="nd-select" id="dq-city" name="city_id">
                            <option value="">농가 기준 자동</option>
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}" @selected((int)old('city_id')===$c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="nd-field">
                        <label for="dq-start">근무 시작 <em>*</em></label>
                        <input class="nd-input" id="dq-start" type="date" name="period_start"
                               value="{{ old('period_start') }}" required>
                    </div>

                    <div class="nd-field">
                        <label for="dq-end">근무 종료 <em>*</em></label>
                        <input class="nd-input" id="dq-end" type="date" name="period_end"
                               value="{{ old('period_end') }}" required>
                    </div>
                </div>

                <div class="nd-field">
                    <label for="dq-note">메모</label>
                    <textarea class="nd-textarea" id="dq-note" name="note" maxlength="2000" rows="3"
                              placeholder="추가 요청사항">{{ old('note') }}</textarea>
                </div>

                <div class="nd-formfoot">
                    <a href="{{ route('demand.index') }}" class="nd-btn nd-btn--line nd-btn--sm">취소</a>
                    <button type="submit" class="nd-btn nd-btn--ink nd-btn--sm">수요 신청 저장</button>
                </div>
            </form>
        </div>
    @endif

    @push('head')
    <style>
        /* 필수 표시 — 라벨 안의 별표 */
        .nd-field em { color: var(--nd-err); font-style: normal; }
    </style>
    @endpush

    @push('scripts')
    <script>
        (function () {
            // 농가를 여러 곳 가진 계정이면 고른 농가로 저장 주소를 바꾼다.
            var form = document.getElementById('dq-form');
            var farm = document.getElementById('dq-farm');
            if (!form || !farm || farm.tagName !== 'SELECT') return;
            function sync() { form.setAttribute('action', form.dataset.base + '/' + farm.value + '/demand'); }
            farm.addEventListener('change', sync);
            sync();
        })();
    </script>
    @endpush
@endsection
