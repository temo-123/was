{{--
    Member card fields on the public check-in pages, all required.
    $only   — which of ClubMember::PROFILE_FIELDS to show
    $values — prefilled values (field => value)
--}}
@php
    $digitsError = e(__('The phone number must contain only digits (9–15).'));
    $nameAttributes = 'minlength="2" maxlength="100" pattern="[\\p{L}][\\p{L}\\s\'\\-]*" data-error="'.e(__('Letters only, at least 2.')).'"';
    // field => [label, type, extra HTML attributes, half width]
    $definitions = [
        'first_name' => [__('First name'), 'text', $nameAttributes.' autocomplete="given-name"', true],
        'last_name' => [__('Last name'), 'text', $nameAttributes.' autocomplete="family-name"', true],
        'personal_id' => [__('ID number'), 'text', 'inputmode="numeric" maxlength="11" pattern="\d{11}" data-digits="11" placeholder="01001012345" data-error="'.e(__('Exactly 11 digits.')).'"', false],
        'birth_date' => [__('Date of birth'), 'date', 'min="1900-01-01" max="'.now()->subDay()->toDateString().'" autocomplete="bday" data-error="'.e(__('Enter a date in the past.')).'"', true],
        'gender' => [__('Gender'), 'select', '', true],
        'phone' => [__('Phone'), 'tel', 'inputmode="numeric" maxlength="15" pattern="\d{9,15}" data-digits="15" placeholder="599123456" autocomplete="tel" data-error="'.$digitsError.'"', false],
        'email' => [__('Email'), 'email', 'maxlength="255" pattern="[^\s@]+@[^\s@]+\.[A-Za-z]{2,}" placeholder="name@gmail.com" autocomplete="email" data-error="'.e(__('Enter a real email address, e.g. name@gmail.com.')).'"', false],
        'emergency_contact_name' => [__('Name'), 'text', 'minlength="2" maxlength="150" pattern="[\\p{L}][\\p{L}\\s\'\\-]*" placeholder="'.e(__('e.g. mother, friend')).'" data-error="'.e(__('Letters only, at least 2.')).'"', true],
        'emergency_contact_phone' => [__('Phone'), 'tel', 'inputmode="numeric" maxlength="15" pattern="\d{9,15}" data-digits="15" placeholder="599123456" data-error="'.$digitsError.'"', true],
    ];
    $emergency = array_values(array_intersect($only, ['emergency_contact_name', 'emergency_contact_phone']));
@endphp
<div class="row g-2">
    @foreach (array_intersect(array_keys($definitions), $only) as $name)
        @php([$label, $type, $attributes, $half] = $definitions[$name])
        @if ($emergency && $name === $emergency[0])
            <div class="col-12 mt-3">
                <div class="fw-semibold small"><i class="bi bi-telephone-plus text-danger"></i> {{ __('Who should we call in an emergency?') }}</div>
            </div>
        @endif
        <div @class(['col-6' => $half, 'col-12' => ! $half])>
            <label for="reg_{{ $name }}" class="form-label small mb-1">{{ $label }} <span class="text-danger">*</span></label>
            @if ($type === 'select')
                <select id="reg_{{ $name }}" name="{{ $name }}" required class="form-select @error($name) is-invalid @enderror">
                    <option value="">—</option>
                    @foreach (\App\Models\ClubMember::GENDERS as $value => $text)
                        <option value="{{ $value }}" @selected(old($name, $values[$name] ?? '') === $value)>{{ __($text) }}</option>
                    @endforeach
                </select>
            @else
                <input id="reg_{{ $name }}" name="{{ $name }}" type="{{ $type }}" required {!! $attributes !!}
                       value="{{ old($name, $values[$name] ?? '') }}" class="form-control @error($name) is-invalid @enderror">
            @endif
            <div class="invalid-feedback">{{ $errors->first($name) }}</div>
        </div>
    @endforeach
</div>
