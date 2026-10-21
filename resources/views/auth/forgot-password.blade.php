@extends('layouts.app')

@section('title', __('Forgot password'))

@section('content')
    <div class="card shadow-sm mx-auto auth-card">
        <div class="card-body p-4">
            <h1 class="h4 mb-3 text-center">{{ __('Forgot password') }}</h1>
            <p class="text-body-secondary small">{{ __('Enter your email and we will send you a link to choose a new password.') }}</p>

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('Email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="form-control @error('email') is-invalid @enderror">
                </div>
                <button class="btn btn-primary w-100">{{ __('Send reset link') }}</button>
            </form>

            <p class="text-center mt-4 mb-0 small"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
        </div>
    </div>
@endsection
