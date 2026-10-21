@extends('layouts.app')

@section('title', __('Sign in'))

@section('content')
    <div class="card shadow-sm mx-auto auth-card">
        <div class="card-body p-4">
            <h1 class="h4 mb-4 text-center">{{ __('Sign in') }}</h1>

            @include('auth.partials.google-button')

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('Email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="form-control @error('email') is-invalid @enderror">
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <label for="password" class="form-label">{{ __('Password') }}</label>
                        <a href="{{ route('password.request') }}" class="small">{{ __('Forgot password?') }}</a>
                    </div>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           class="form-control @error('password') is-invalid @enderror">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                    <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
                </div>
                <button class="btn btn-primary w-100">{{ __('Sign in') }}</button>
            </form>

            <p class="text-center mt-4 mb-0 small">
                {{ __('No account yet?') }} <a href="{{ route('register') }}">{{ __('Register') }}</a>
            </p>
        </div>
    </div>
@endsection
