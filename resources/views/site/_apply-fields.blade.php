{{--
    본인 정보 입력칸 — 지원하기·보완 제출·본인 정보 수정이 함께 쓴다.

    $mode:
      'apply'      가입 (전부 필수, 비어 있음)
      'supplement' 보완 (전부 선택. 민감 항목은 미리 채우지 않는다 — 로그인 없이 열리는 화면이라 §7-1)
      'profile'    본인 화면 (전부 선택. 로그인했으므로 민감 항목도 채워 준다)

    $prefill: 미리 채울 값 배열
    $cities:  고를 수 있는 지역 ('apply' 에서만 쓴다)

    지역은 가입할 때만 고른다. 그 뒤 **어느 농가에서 일할지는 관리자가 정한다** —
    수정 화면에 지역 칸을 두면 근로자가 스스로 배치를 바꾸는 것처럼 보인다.
--}}
@php
    use App\Domains\Recruitment\Enums\Nationality;
    use App\Shared\Translation\SiteTranslator;

    $req = $mode === 'apply';
    $showSensitive = $mode === 'profile';   // 로그인한 화면에서만 기존 값을 되돌려 준다
    $star = $req ? '<span class="nd-req">*</span>' : '';
@endphp

<div class="nd-field">
    <label for="f-name">이름(여권 영문) {!! $star !!}</label>
    <input class="nd-input @error('name') is-bad @enderror" id="f-name" type="text"
           name="name" value="{{ old('name', $prefill['name'] ?? '') }}" maxlength="100" @required($req)>
    @error('name')<p class="nd-err">{{ $message }}</p>@enderror
</div>

<div class="nd-field">
    <label for="f-nationality">국적 {!! $star !!}</label>
    {{-- 나라·언어 이름은 번역기에 맡기지 않는다. 기계 번역이 자주 틀리고,
         자기 나라 이름을 못 알아보면 가입 자체가 막힌다. --}}
    <select class="nd-input @error('nationality') is-bad @enderror" id="f-nationality"
            name="nationality" data-no-translate @required($req)>
        <option value="">{{ $req ? '선택하세요' : '그대로 두기' }}</option>
        @foreach (Nationality::options() as $code => $label)
            <option value="{{ $code }}" @selected(old('nationality', $prefill['nationality'] ?? '') === $code)>{{ $label }}</option>
        @endforeach
    </select>
    @error('nationality')<p class="nd-err">{{ $message }}</p>@enderror
</div>

<div class="nd-field">
    <label for="f-locale">사용 언어 {!! $star !!}</label>
    <select class="nd-input @error('locale') is-bad @enderror" id="f-locale"
            name="locale" data-no-translate @required($req)>
        @unless ($req)<option value="">그대로 두기</option>@endunless
        @foreach (SiteTranslator::NATIVE as $code => $label)
            @continue($code === 'en')
            <option value="{{ $code }}" @selected(old('locale', $prefill['locale'] ?? ($req ? app()->getLocale() : '')) === $code)>{{ $label }}</option>
        @endforeach
    </select>
    <p class="nd-help">알림과 안내를 이 언어로 보내 드립니다.</p>
    @error('locale')<p class="nd-err">{{ $message }}</p>@enderror
</div>

@if ($req)
    {{-- 지원 지역은 가입할 때만. 지역별 모집 정원이 따로 운영되므로 여기서 확정한다. --}}
    <div class="nd-field">
        <label for="f-city">지원 지역 <span class="nd-req">*</span></label>
        <select class="nd-input @error('city_id') is-bad @enderror" id="f-city" name="city_id" required>
            <option value="">선택하세요</option>
            @foreach ($cities as $c)
                <option value="{{ $c['value'] }}"
                    @selected((string) old('city_id', $prefill['city_id'] ?? '') === (string) $c['value'])>{{ $c['label'] }}</option>
            @endforeach
        </select>
        <p class="nd-help">
            지원하고 싶은 지역입니다. <b>실제로 일할 농가는 담당자가 정해 알려 드립니다.</b>
        </p>
        @if (! count($cities))
            <p class="nd-help">지금은 모집 중인 지역이 없습니다. 담당자에게 문의해 주세요.</p>
        @endif
        @error('city_id')<p class="nd-err">{{ $message }}</p>@enderror
    </div>
@endif

<div class="nd-field">
    <label for="f-passport">여권번호 {!! $star !!}</label>
    <input class="nd-input @error('passport_no') is-bad @enderror" id="f-passport" type="text"
           name="passport_no" value="{{ old('passport_no', $showSensitive ? ($prefill['passport_no'] ?? '') : '') }}"
           maxlength="64" @required($req)>
    <p class="nd-help">
        @if ($req)
            암호화해서 보관하며 담당자만 볼 수 있습니다. 같은 번호로는 한 번만 신청할 수 있습니다.
        @elseif ($showSensitive)
            바꾸려면 새 번호를 적으세요. 같은 번호를 다른 사람이 쓰고 있으면 저장되지 않습니다.
        @else
            <b>보안을 위해 기존 번호는 표시하지 않습니다.</b> 바꿀 때만 새 번호를 적으세요. 비워 두면 그대로 유지됩니다.
        @endif
    </p>
    @error('passport_no')<p class="nd-err">{{ $message }}</p>@enderror
</div>

<div class="nd-field">
    <label for="f-birth">생년월일</label>
    <input class="nd-input @error('birth_date') is-bad @enderror" id="f-birth" type="date"
           name="birth_date" value="{{ old('birth_date', $showSensitive ? ($prefill['birth_date'] ?? '') : '') }}">
    @unless ($req || $showSensitive)
        <p class="nd-help">비워 두면 그대로 유지됩니다.</p>
    @endunless
    @error('birth_date')<p class="nd-err">{{ $message }}</p>@enderror
</div>

<div class="nd-field">
    <label for="f-phone">본국 전화번호</label>
    <input class="nd-input @error('phone_home_country') is-bad @enderror" id="f-phone" type="tel"
           name="phone_home_country"
           value="{{ old('phone_home_country', $showSensitive ? ($prefill['phone_home_country'] ?? '') : '') }}"
           maxlength="40">
    @unless ($req || $showSensitive)
        <p class="nd-help">비워 두면 그대로 유지됩니다.</p>
    @endunless
    @error('phone_home_country')<p class="nd-err">{{ $message }}</p>@enderror
</div>
