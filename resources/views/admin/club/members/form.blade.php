@extends('layouts.admin')

@section('title', $member->exists ? __('Edit club member') : __('New club member'))

@section('content')
    @if ($member->is_pending)
        <div class="card border-warning mb-3">
            <div class="card-body">
                <p class="mb-2">
                    <i class="bi bi-person-plus text-warning"></i>
                    {{ __('This person registered themselves at a meeting on :date and is not in the member list yet.', ['date' => $member->created_at->format('d.m.Y H:i')]) }}
                </p>
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <form method="POST" action="{{ route('admin.club.members.approve', $member) }}">
                        @csrf
                        <button class="btn btn-success"><i class="bi bi-person-check"></i> {{ __('Add to the member list') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.club.members.merge', $member) }}" class="d-flex gap-2 flex-grow-1" style="max-width: 520px"
                          onsubmit="return confirm(@js(__('Move the attendance to the chosen member and delete this copy?')))">
                        @csrf
                        <select name="target_id" class="form-select" required>
                            <option value="">{{ __('…or merge into an existing member') }}</option>
                            @if ($similar->isNotEmpty())
                                <optgroup label="{{ __('Looks the same') }}">
                                    @foreach ($similar as $target)
                                        <option value="{{ $target->id }}">{{ $target->full_name }} @if ($target->phone) ({{ $target->phone }}) @endif</option>
                                    @endforeach
                                </optgroup>
                            @endif
                            <optgroup label="{{ __('All members') }}">
                                @foreach ($mergeTargets as $target)
                                    <option value="{{ $target->id }}">{{ $target->full_name }} @if ($target->phone) ({{ $target->phone }}) @endif</option>
                                @endforeach
                            </optgroup>
                        </select>
                        <button class="btn btn-outline-primary text-nowrap"><i class="bi bi-intersect"></i> {{ __('Merge') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($member->exists && ($missing = $member->missingProfileFields()))
        <div class="alert alert-warning">
            <i class="bi bi-person-vcard"></i>
            {{ __('Incomplete card — the member will be asked for the missing details at their next check-in:') }}
            {{ collect($missing)->map(fn ($field) => __(\App\Services\Club\MemberSpreadsheet::COLUMNS[$field]))->implode(', ') }}
        </div>
    @endif

    <form method="POST" action="{{ $member->exists ? route('admin.club.members.update', $member) : route('admin.club.members.store') }}" class="card" novalidate data-validate>
        @csrf
        @if ($member->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="row g-3">
                @php
                    // [field, label, type, required, extra HTML attributes, hint]
                    $fields = [
                        ['first_name', __('First name'), 'text', true, 'minlength="2" maxlength="100" pattern="[\\p{L}][\\p{L}\\s\'\\-]*" data-error="'.e(__('Letters only, at least 2.')).'"', null],
                        ['last_name', __('Last name'), 'text', true, 'minlength="2" maxlength="100" pattern="[\\p{L}][\\p{L}\\s\'\\-]*" data-error="'.e(__('Letters only, at least 2.')).'"', null],
                        ['personal_id', __('ID number'), 'text', false, 'inputmode="numeric" maxlength="11" pattern="\\d{11}" data-digits="11" placeholder="01001012345" data-error="'.e(__('Exactly 11 digits.')).'"', __('11 digits')],
                        ['phone', __('Phone'), 'tel', false, 'inputmode="numeric" maxlength="15" pattern="\\d{9,15}" data-digits="15" placeholder="599123456" data-error="'.e(__('Digits only, 9–15.')).'"', __('digits only')],
                        ['email', __('Email'), 'email', false, 'maxlength="255" pattern="[^\\s@]+@[^\\s@]+\\.[A-Za-z]{2,}" placeholder="name@gmail.com" data-error="'.e(__('Enter a real email, e.g. name@gmail.com.')).'"', __('optional')],
                        ['birth_date', __('Date of birth'), 'date', false, 'min="1900-01-01" max="'.now()->subDay()->toDateString().'" data-error="'.e(__('Enter a date in the past.')).'"', __('optional')],
                        ['gender', __('Gender'), 'select', false, '', __('optional')],
                        ['emergency_contact_name', __('Emergency contact'), 'text', false, 'minlength="2" maxlength="150" pattern="[\\p{L}][\\p{L}\\s\'\\-]*" data-error="'.e(__('Letters only, at least 2.')).'"', __('optional')],
                        ['emergency_contact_phone', __('Emergency contact phone'), 'tel', false, 'inputmode="numeric" maxlength="15" pattern="\\d{9,15}" data-digits="15" placeholder="599123456" data-error="'.e(__('Digits only, 9–15.')).'"', __('optional')],
                        ['joined_on', __('Member since'), 'date', false, 'min="1900-01-01"', __('optional')],
                    ];
                @endphp
                @foreach ($fields as [$field, $label, $type, $required, $attributes, $hint])
                    <div class="col-md-6">
                        <label for="{{ $field }}" class="form-label">
                            {{ $label }} @if ($required)<span class="text-danger">*</span>@endif
                            @if ($hint)<small class="text-body-secondary">({{ $hint }})</small>@endif
                        </label>
                        @if ($type === 'select')
                            <select id="{{ $field }}" name="{{ $field }}" @class(['form-select', 'is-invalid' => $errors->has($field)])>
                                <option value="">—</option>
                                @foreach (\App\Models\ClubMember::GENDERS as $value => $text)
                                    <option value="{{ $value }}" @selected(old($field, $member->{$field}) === $value)>{{ __($text) }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" @required($required) {!! $attributes !!} autocomplete="off"
                                   value="{{ old($field, in_array($field, ['birth_date', 'joined_on']) ? $member->{$field}?->toDateString() : $member->{$field}) }}"
                                   @class(['form-control', 'font-monospace' => in_array($field, ['personal_id', 'phone', 'emergency_contact_phone']), 'is-invalid' => $errors->has($field)])>
                        @endif
                        <div class="invalid-feedback">{{ $errors->first($field) }}</div>
                    </div>
                @endforeach
                <div class="col-12">
                    <div class="form-text"><i class="bi bi-info-circle"></i> {{ __('Members check in at meetings with their ID number or phone, so enter at least one of them.') }}</div>
                </div>

                <div class="col-12">
                    <label for="comment" class="form-label">{{ __('Comment') }}</label>
                    <textarea id="comment" name="comment" rows="3" class="form-control @error('comment') is-invalid @enderror">{{ old('comment', $member->comment) }}</textarea>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $member->is_active))>
                        <label class="form-check-label" for="is_active">{{ __('Active member (can check in at meetings)') }}</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('admin.club.members.index', $member->is_pending ? ['tab' => 'new'] : []) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            @if ($member->exists)
                <button form="delete-member" class="btn btn-outline-danger ms-auto" onclick="return confirm(@js(__('Delete this member and their attendance history?')))">
                    <i class="bi bi-trash"></i> {{ __('Delete') }}
                </button>
            @endif
        </div>
    </form>

    @if ($member->exists)
        <form id="delete-member" method="POST" action="{{ route('admin.club.members.destroy', $member) }}">@csrf @method('DELETE')</form>

        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title mb-0">{{ __('Meetings attended') }}</h3></div>
            <ul class="list-group list-group-flush">
                @forelse ($attendances as $attendance)
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="{{ route('admin.club.meetings.show', $attendance->meeting) }}">{{ $attendance->meeting->title }}</a>
                        <span class="text-body-secondary">{{ $attendance->checked_in_at->format('d.m.Y H:i') }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('No meetings yet.') }}</li>
                @endforelse
            </ul>
        </div>
    @endif
@endsection
