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
                    {{-- 1. Name search --}}
                    <form method="GET" action="{{ route('club.checkin', $meeting) }}">
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="first_name" class="form-label">{{ __('First name') }}</label>
                                <input id="first_name" name="first_name" required minlength="2" maxlength="100" autocomplete="given-name" @if (! $search) autofocus @endif
                                       value="{{ $search['first_name'] ?? old('first_name') }}" class="form-control form-control-lg @error('first_name') is-invalid @enderror">
                            </div>
                            <div class="col-6">
                                <label for="last_name" class="form-label">{{ __('Last name') }}</label>
                                <input id="last_name" name="last_name" required minlength="2" maxlength="100" autocomplete="family-name"
                                       value="{{ $search['last_name'] ?? old('last_name') }}" class="form-control form-control-lg @error('last_name') is-invalid @enderror">
                            </div>
                        </div>
                        <button @class(['btn btn-lg w-100 mt-3', 'btn-primary' => ! $search, 'btn-outline-primary' => $search])>
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
                                        <input type="hidden" name="first_name" value="{{ $search['first_name'] }}">
                                        <input type="hidden" name="last_name" value="{{ $search['last_name'] }}">
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
                                <i class="bi bi-info-circle"></i> {{ __('We could not find :name in the member list.', ['name' => $search['first_name'].' '.$search['last_name']]) }}
                            </div>
                        @endif

                        {{-- 3. Not registered yet --}}
                        <form method="POST" action="{{ route('club.checkin.store', $meeting) }}" class="mt-3 p-3 bg-body-tertiary rounded">
                            @csrf
                            <input type="hidden" name="new" value="1">
                            <input type="hidden" name="first_name" value="{{ $search['first_name'] }}">
                            <input type="hidden" name="last_name" value="{{ $search['last_name'] }}">
                            <p class="mb-2">{{ $matches->isNotEmpty() ? __('Not in the list?') : __('You are not registered yet — no problem.') }}</p>
                            <label for="phone" class="form-label small">{{ __('Phone') }} <span class="text-body-secondary">({{ __('optional, helps us find you next time') }})</span></label>
                            <input id="phone" name="phone" type="tel" inputmode="numeric" maxlength="20" placeholder="599123456" class="form-control mb-2">
                            <button class="btn btn-warning w-100"><i class="bi bi-person-plus"></i> {{ __('I\'m not registered yet — check in anyway') }}</button>
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
