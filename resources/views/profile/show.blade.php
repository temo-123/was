@extends('layouts.app')

@section('title', __('My profile'))

@section('content')
    @unless ($user->hasVerifiedEmail())
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <span><i class="bi bi-envelope-exclamation me-1"></i> {{ __('Your email address is not verified yet.') }}</span>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn btn-sm btn-warning">{{ __('Send verification link') }}</button>
            </form>
        </div>
    @endunless

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-body text-center">
                    <img src="{{ $user->avatarUrl() }}" class="rounded-circle mb-3" width="96" height="96" alt="">
                    <h1 class="h5 mb-1">{{ $user->name }}</h1>
                    <div class="text-body-secondary small">{{ $user->email }}</div>
                    <div class="mt-2">
                        @foreach ($user->roles as $role)
                            <span class="badge text-bg-secondary">{{ __($role->display_name) }}</span>
                        @endforeach
                    </div>
                    @if ($user->canAccessAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-primary mt-3"><i class="bi bi-speedometer2"></i> {{ __('Admin panel') }}</a>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-semibold"><i class="bi bi-person-vcard me-1"></i> {{ __('Gym card') }}</div>
                <div class="card-body">
                    @if ($visitor)
                        <dl class="row small mb-0">
                            <dt class="col-5">{{ __('Name') }}</dt><dd class="col-7">{{ $visitor->full_name }}</dd>
                            <dt class="col-5">{{ __('ID number') }}</dt><dd class="col-7">{{ $visitor->personal_id }}</dd>
                            <dt class="col-5">{{ __('Category') }}</dt><dd class="col-7">{{ $visitor->category?->local_name ?? '—' }}</dd>
                            <dt class="col-5">{{ __('Visits') }}</dt><dd class="col-7">{{ $visitor->visits()->count() }}</dd>
                        </dl>
                    @else
                        <p class="small text-body-secondary mb-0">
                            {{ __('Your account is not connected to a gym card yet. Add your ID number below (or ask the reception) and your visits will appear here.') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            @if ($visitor)
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold"><i class="bi bi-card-checklist me-1"></i> {{ __('Subscriptions') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Hours') }}</th><th>{{ __('Valid') }}</th><th>{{ __('Status') }}</th></tr></thead>
                            <tbody>
                            @forelse ($subscriptions as $sub)
                                <tr>
                                    <td>{{ $sub->ticketType->local_name }}</td>
                                    <td>{{ $sub->timeSlot->label() }}</td>
                                    <td>{{ $sub->starts_on->format('d.m.Y') }} – {{ $sub->ends_on->format('d.m.Y') }}</td>
                                    <td>
                                        @if ($sub->isActive())<span class="badge text-bg-success">{{ __('active') }}</span>
                                        @elseif ($sub->cancelled_at)<span class="badge text-bg-secondary">{{ __('cancelled') }}</span>
                                        @else<span class="badge text-bg-light">{{ __('expired') }}</span>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-body-secondary">{{ __('No subscriptions.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold"><i class="bi bi-calendar-check me-1"></i> {{ __('Last visits') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Ticket') }}</th><th class="text-end">{{ __('Price') }}</th></tr></thead>
                            <tbody>
                            @forelse ($visits as $visit)
                                <tr>
                                    <td>{{ $visit->entered_at->format('d.m.Y H:i') }}</td>
                                    <td>{{ $visit->ticketType?->local_name }}</td>
                                    <td class="text-end">{{ $visit->payment_method === 'subscription' ? __('subscription') : number_format($visit->price, 2).' ₾' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-body-secondary">{{ __('No visits yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">{{ __('Profile details') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}" novalidate data-validate>
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">{{ __('Full name') }}</label>
                                <input id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">{{ __('Email') }}</label>
                                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="phone">{{ __('Phone') }}</label>
                                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="personal_id">{{ __('ID number') }}</label>
                                <input id="personal_id" name="personal_id" inputmode="numeric" maxlength="11" pattern="\d{11}" data-digits="11" placeholder="01001012345" data-error="{{ __('Exactly 11 digits.') }}" value="{{ old('personal_id', $user->personal_id) }}" class="form-control @error('personal_id') is-invalid @enderror" @disabled($visitor)>
                                <div class="invalid-feedback">{{ $errors->first('personal_id') }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="birth_date">{{ __('Date of birth') }}</label>
                                <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date', $user->birth_date?->toDateString()) }}" class="form-control @error('birth_date') is-invalid @enderror">
                            </div>
                        </div>
                        <button class="btn btn-primary mt-3">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">{{ $user->hasPassword() ? __('Change password') : __('Set a password') }}</div>
                <div class="card-body">
                    @unless ($user->hasPassword())
                        <p class="small text-body-secondary">{{ __('You signed up with Google. Set a password if you also want to sign in with email.') }}</p>
                    @endunless
                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            @if ($user->hasPassword())
                                <div class="col-md-4">
                                    <label class="form-label" for="current_password">{{ __('Current password') }}</label>
                                    <input id="current_password" type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
                                </div>
                            @endif
                            <div class="col-md-4">
                                <label class="form-label" for="password">{{ __('New password') }}</label>
                                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="password_confirmation">{{ __('Confirm password') }}</label>
                                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                            </div>
                        </div>
                        <button class="btn btn-primary mt-3">{{ __('Update password') }}</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-danger">
                <div class="card-header fw-semibold text-danger">{{ __('Delete account') }}</div>
                <div class="card-body">
                    <p class="small text-body-secondary">{{ __('Your login is deleted permanently. Your visit history stays with the gym as described in the privacy policy.') }}</p>
                    <form method="POST" action="{{ route('profile.destroy') }}" class="row g-2 align-items-end" data-confirm="{{ __('Delete your account permanently?') }}">
                        @csrf @method('DELETE')
                        <div class="col-md-8">
                            <label class="form-label" for="confirm_email">{{ __('Type your email to confirm') }}</label>
                            <input id="confirm_email" name="confirm_email" class="form-control @error('confirm_email') is-invalid @enderror">
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-danger w-100">{{ __('Delete account') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
