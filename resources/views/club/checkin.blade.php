@extends('layouts.app')

@section('title', __('Check-in'))

@section('content')
    <div class="mx-auto" style="max-width: 480px">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="text-center">
                    <i class="bi bi-people-fill display-5 text-primary"></i>
                    <h1 class="h4 mt-2 mb-0">{{ $meeting->title }}</h1>
                    <p class="text-body-secondary">{{ $meeting->starts_at->format('d.m.Y H:i') }} @if ($meeting->location) · {{ $meeting->location }} @endif</p>
                </div>

                @if (session('checked_in'))
                    <div class="alert alert-success fs-5 text-center"><i class="bi bi-check-circle-fill"></i> {{ session('checked_in') }}</div>
                @elseif ($attendance)
                    <div class="alert alert-success text-center">
                        <i class="bi bi-check-circle-fill"></i>
                        {{ __(':name, you were already checked in at :time.', ['name' => $member->first_name, 'time' => $attendance->checked_in_at->format('H:i')]) }}
                    </div>
                @elseif (! $meeting->is_open)
                    <div class="alert alert-secondary text-center"><i class="bi bi-lock"></i> {{ __('Check-in for this meeting is closed.') }}</div>
                @elseif (! $allowed)
                    <div class="alert alert-warning text-center"><i class="bi bi-qr-code-scan"></i> {{ __('Please scan the QR code shown at the meeting.') }}</div>
                @elseif ($member && $matches === null)
                    {{-- This phone was used before: one tap --}}
                    <form method="POST" action="{{ route('club.checkin.store', $meeting) }}">
                        @csrf
                        <input type="hidden" name="remembered" value="1">
                        <button class="btn btn-success btn-lg w-100 py-3"><i class="bi bi-check2-circle"></i> {{ __('Check in as :name', ['name' => $member->full_name]) }}</button>
                    </form>
                    <form method="POST" action="{{ route('club.checkin.forget', $meeting) }}" class="mt-3 text-center">
                        @csrf
                        <button class="btn btn-link btn-sm">{{ __('This is not me') }}</button>
                    </form>
                @else
                    {{-- 1. Search by name, phone, email or ID number --}}
                    <form method="GET" action="{{ route('club.checkin', $meeting) }}">
                        <label for="q" class="form-label">{{ __('Your name, phone, email or ID number') }}</label>
                        <input id="q" name="q" required minlength="2" maxlength="255" autocomplete="off" @if ($search === null) autofocus @endif
                               value="{{ $search ?? old('q') }}" placeholder="{{ __('e.g. first and last name, or 599123456') }}"
                               class="form-control form-control-lg @error('q') is-invalid @enderror">
                        @error('q')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button @class(['btn btn-lg w-100 mt-3', 'btn-primary' => $search === null, 'btn-outline-primary' => $search !== null])>
                            <i class="bi bi-search"></i> {{ __('Find me') }}
                        </button>
                    </form>

                    @if ($matches !== null)
                        <hr>
                        {{-- 2. Pick yourself --}}
                        @if ($matches->isNotEmpty())
                            <p class="fw-semibold mb-2">{{ __('Is this you? Tap your name:') }}</p>
                            <div class="d-grid gap-2">
                                @foreach ($matches as $match)
                                    <form method="POST" action="{{ route('club.checkin.store', $meeting) }}">
                                        @csrf
                                        <input type="hidden" name="member_id" value="{{ $match->id }}">
                                        <input type="hidden" name="q" value="{{ $search }}">
                                        <button class="btn btn-success btn-lg w-100 d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-person-check"></i> {{ $match->full_name }}</span>
                                            <small class="opacity-75">
                                                @if ($match->phone_hint){{ $match->phone_hint }}@endif
                                                @if ($match->birth_date) · {{ $match->birth_date->year }}@endif
                                            </small>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i> {{ __('We could not find ":search" in the member list.', ['search' => $search]) }}
                            </div>
                        @endif

                        {{-- 3. Not registered yet: all fields required --}}
                        <form method="POST" action="{{ route('club.checkin.store', $meeting) }}" class="mt-3 p-3 bg-body-tertiary rounded" novalidate data-validate>
                            @csrf
                            <input type="hidden" name="new" value="1">
                            <input type="hidden" name="q" value="{{ $search }}">
                            <p class="mb-2">{{ $matches->isNotEmpty() ? __('Not in the list? Register:') : __('You are not registered yet — no problem, fill in your details:') }}</p>
                            @php
                                $fields = [
                                    ['first_name', __('First name'), 'text', 'minlength="2" maxlength="100" autocomplete="given-name"'],
                                    ['last_name', __('Last name'), 'text', 'minlength="2" maxlength="100" autocomplete="family-name"'],
                                    ['personal_id', __('ID number'), 'text', 'inputmode="numeric" maxlength="11" pattern="\d{11}" data-digits="11" placeholder="01001012345" data-error="'.e(__('Exactly 11 digits.')).'"'],
                                    ['phone', __('Phone'), 'tel', 'inputmode="numeric" maxlength="15" pattern="\d{9,15}" data-digits="15" placeholder="599123456" autocomplete="tel" data-error="'.e(__('The phone number must contain only digits (9–15).')).'"'],
                                    ['email', __('Email'), 'email', 'maxlength="255" placeholder="name@gmail.com" autocomplete="email" data-error="'.e(__('Enter a real email address, e.g. name@gmail.com.')).'"'],
                                ];
                            @endphp
                            <div class="row g-2">
                                @foreach ($fields as [$name, $label, $type, $attributes])
                                    <div @class(['col-6' => in_array($name, ['first_name', 'last_name']), 'col-12' => ! in_array($name, ['first_name', 'last_name'])])>
                                        <label for="reg_{{ $name }}" class="form-label small mb-1">{{ $label }} <span class="text-danger">*</span></label>
                                        <input id="reg_{{ $name }}" name="{{ $name }}" type="{{ $type }}" required {!! $attributes !!}
                                               value="{{ old($name, $prefill[$name] ?? '') }}" class="form-control @error($name) is-invalid @enderror">
                                        <div class="invalid-feedback">{{ $errors->first($name) }}</div>
                                    </div>
                                @endforeach
                            </div>
                            <button class="btn btn-warning w-100 mt-3"><i class="bi bi-person-plus"></i> {{ __('I\'m not registered yet — check in anyway') }}</button>
                        </form>
                    @endif

                    @if ($member)
                        <p class="text-center mt-3 mb-0 small">
                            <a href="{{ route('club.checkin', $meeting) }}">{{ __('Back to: check in as :name', ['name' => $member->full_name]) }}</a>
                        </p>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
