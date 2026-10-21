@extends('layouts.app')

@section('title', __('Reset password'))

@section('content')
    <div class="card shadow-sm mx-auto auth-card">
        <div class="card-body p-4">
            <h1 class="h4 mb-4 text-center">{{ __('Choose a new password') }}</h1>

            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('Email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username"
                           class="form-control @error('email') is-invalid @enderror">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('New password') }}</label>
                    <input id="password" type="password" name="password" required autofocus autocomplete="new-password"
                           class="form-control @error('password') is-invalid @enderror">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-control">
                </div>
                <button class="btn btn-primary w-100">{{ __('Reset password') }}</button>
            </form>
        </div>
    </div>
@endsection
