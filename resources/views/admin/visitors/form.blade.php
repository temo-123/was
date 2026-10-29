@extends('layouts.admin')

@section('title', $visitor->exists ? __('Edit visitor') : __('New visitor'))

@section('content')
    <form method="POST" action="{{ $visitor->exists ? route('admin.visitors.update', $visitor) : route('admin.visitors.store') }}" class="card" novalidate data-validate>
        @csrf
        @if ($visitor->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="row g-3">
                @php
                    // [field, label, type, required, extra HTML attributes, hint]
                    $fields = [
                        ['first_name', __('First name'), 'text', true, 'minlength="2" maxlength="100" pattern="[\\p{L}][\\p{L}\\s\'\\-]*" data-error="'.e(__('Letters only, at least 2.')).'"', null],
                        ['last_name', __('Last name'), 'text', true, 'minlength="2" maxlength="100" pattern="[\\p{L}][\\p{L}\\s\'\\-]*" data-error="'.e(__('Letters only, at least 2.')).'"', null],
                        ['personal_id', __('ID number'), 'text', true, 'inputmode="numeric" maxlength="11" pattern="\\d{11}" data-digits="11" placeholder="01001012345" data-error="'.e(__('Exactly 11 digits.')).'"', __('11 digits')],
                        ['birth_date', __('Date of birth'), 'date', true, 'min="1900-01-01" max="'.now()->subDay()->toDateString().'" data-error="'.e(__('Enter a date in the past.')).'"', null],
                        ['phone', __('Phone'), 'tel', true, 'inputmode="numeric" maxlength="15" pattern="\\d{9,15}" data-digits="15" placeholder="599123456" data-error="'.e(__('Digits only, 9–15.')).'"', __('digits only')],
                        ['email', __('Email'), 'email', false, 'maxlength="255" pattern="[^\\s@]+@[^\\s@]+\\.[A-Za-z]{2,}" placeholder="name@gmail.com" data-error="'.e(__('Enter a real email, e.g. name@gmail.com.')).'"', __('optional')],
                    ];
                @endphp
                @foreach ($fields as [$field, $label, $type, $required, $attributes, $hint])
                    <div class="col-md-6">
                        <label for="{{ $field }}" class="form-label">
                            {{ $label }} @if ($required)<span class="text-danger">*</span>@endif
                            @if ($hint)<small class="text-body-secondary">({{ $hint }})</small>@endif
                        </label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" @required($required) {!! $attributes !!} autocomplete="off"
                               value="{{ old($field, $field === 'birth_date' ? $visitor->birth_date?->toDateString() : $visitor->{$field}) }}"
                               @class(['form-control', 'font-monospace' => in_array($field, ['personal_id', 'phone']), 'is-invalid' => $errors->has($field)])>
                        <div class="invalid-feedback">{{ $errors->first($field) }}</div>
                    </div>
                @endforeach

                <div class="col-md-6">
                    <label for="visitor_category_id" class="form-label">{{ __('Category') }}</label>
                    <select id="visitor_category_id" name="visitor_category_id" class="form-select">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('visitor_category_id', $visitor->visitor_category_id ?? $categories->firstWhere('is_default', true)?->id) == $category->id)>
                                {{ $category->local_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label for="comment" class="form-label">{{ __('Comment') }}</label>
                    <textarea id="comment" name="comment" rows="3" class="form-control @error('comment') is-invalid @enderror">{{ old('comment', $visitor->comment) }}</textarea>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="terms_accepted" value="0">
                        <input class="form-check-input @error('terms_accepted') is-invalid @enderror" type="checkbox" id="terms_accepted" name="terms_accepted" value="1"
                            @checked(old('terms_accepted', $visitor->terms_accepted))>
                        <label class="form-check-label" for="terms_accepted">
                            {{ __('Visitor has read and accepted the') }} <a href="{{ route('terms') }}" target="_blank">{{ __('gym terms & safety rules') }}</a>
                            @if ($visitor->terms_accepted_at)
                                <small class="text-body-secondary">({{ $visitor->terms_accepted_at->format('d.m.Y H:i') }})</small>
                            @endif
                        </label>
                        @error('terms_accepted')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $visitor->exists ? route('admin.visitors.show', $visitor) : route('admin.visitors.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
