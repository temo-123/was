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

                @if (session('checked_in') || $attendance)
                    @if (session('checked_in'))
                        <div class="alert alert-success fs-5 text-center"><i class="bi bi-check-circle-fill"></i> {{ session('checked_in') }}</div>
                    @else
                        <div class="alert alert-success text-center">
                            <i class="bi bi-check-circle-fill"></i>
                            {{ __(':name, you were already checked in at :time.', ['name' => $member->first_name, 'time' => $attendance->checked_in_at->format('H:i')]) }}
                        </div>
                    @endif
                    @if ($canCompleteProfile)
                        <a href="{{ route('club.checkin.profile', $meeting) }}" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-vcard"></i> {{ __('Complete your details') }}
                        </a>
                    @endif
                @elseif (! $meeting->is_open)
                    <div class="alert alert-secondary text-center"><i class="bi bi-lock"></i> {{ __('Check-in for this meeting is closed.') }}</div>
                @elseif (! $allowed)
                    <div class="alert alert-warning text-center"><i class="bi bi-qr-code-scan"></i> {{ __('Please scan the QR code shown at the meeting.') }}</div>
                @elseif ($member && $found === null)
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
                        <label for="q" class="form-label">{{ __('Your full name, phone, email or ID number') }}</label>
                        <input id="q" name="q" required minlength="2" maxlength="255" autocomplete="off" @if ($search === null) autofocus @endif
                               value="{{ $search ?? old('q') }}" placeholder="{{ __('e.g. Nino Beridze, or 599123456') }}"
                               class="form-control form-control-lg @error('q') is-invalid @enderror">
                        @error('q')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button @class(['btn btn-lg w-100 mt-3', 'btn-primary' => $search === null, 'btn-outline-primary' => $search !== null])>
                            <i class="bi bi-search"></i> {{ __('Find me') }}
                        </button>
                    </form>

                    @if ($found !== null)
                        <hr>
                        {{-- 2. Found or not — nobody's data is shown here --}}
                        @if ($found === 1)
                            <div class="alert alert-success text-center mb-3">
                                <i class="bi bi-person-check-fill"></i> {{ __('We found you in the member list.') }}
                            </div>
                            <form method="POST" action="{{ route('club.checkin.store', $meeting) }}">
                                @csrf
                                <input type="hidden" name="found" value="1">
                                <input type="hidden" name="q" value="{{ $search }}">
                                <button class="btn btn-success btn-lg w-100 py-3"><i class="bi bi-check2-circle"></i> {{ __('Check me in') }}</button>
                            </form>
                            <p class="small text-body-secondary text-center mt-2 mb-0">{{ __('Not you? Search with your ID number or phone instead.') }}</p>
                        @elseif ($found > 1)
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-people"></i> {{ __('More than one member has this name. Please search with your ID number, phone or email instead.') }}
                            </div>
                        @else
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i> {{ __('We could not find ":search" in the member list. Check the spelling, or try your ID number or phone.', ['search' => $search]) }}
                            </div>
                        @endif

                        @if ($found === 0)
                            {{-- 3. Not registered yet: all fields required --}}
                            <form method="POST" action="{{ route('club.checkin.store', $meeting) }}" class="mt-3 p-3 bg-body-tertiary rounded" novalidate data-validate>
                                @csrf
                                <input type="hidden" name="new" value="1">
                                <input type="hidden" name="q" value="{{ $search }}">
                                <p class="mb-2">{{ __('Not registered yet? No problem, fill in your details:') }}</p>
                                @include('club._profile-fields', ['only' => \App\Models\ClubMember::PROFILE_FIELDS, 'values' => $prefill])
                                <button class="btn btn-warning w-100 mt-3"><i class="bi bi-person-plus"></i> {{ __('I\'m not registered yet — check in anyway') }}</button>
                            </form>
                        @endif
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
