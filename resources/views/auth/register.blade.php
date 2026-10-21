@extends('layouts.app')

@section('title', __('Register'))

@section('content')
    <div class="card shadow-sm mx-auto auth-card">
        <div class="card-body p-4">
            <h1 class="h4 mb-4 text-center">{{ __('Create an account') }}</h1>

            @include('auth.partials.google-button', ['label' => __('Sign up with Google')])

            <form method="POST" action="{{ route('register') }}" novalidate data-validate>
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">{{ __('Full name') }}</label>
                    <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('Email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <label for="personal_id" class="form-label">{{ __('ID number') }} <small class="text-body-secondary">({{ __('optional') }})</small></label>
                        <input id="personal_id" name="personal_id" inputmode="numeric" maxlength="11" pattern="\d{11}" data-digits="11" placeholder="01001012345" data-error="{{ __('Exactly 11 digits.') }}" value="{{ old('personal_id') }}"
                               class="form-control @error('personal_id') is-invalid @enderror">
                        @error('personal_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6 mb-3">
                        <label for="phone" class="form-label">{{ __('Phone') }} <small class="text-body-secondary">({{ __('optional') }})</small></label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel"
                               class="form-control @error('phone') is-invalid @enderror">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-text mt-n2 mb-3">{{ __('If you have already visited the gym, your ID number connects this account with your visit history.') }}</div>
                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Password') }}</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                           class="form-control @error('password') is-invalid @enderror">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-control">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" @checked(old('terms'))>
                    <label class="form-check-label" for="terms">
                        {!! __('I agree to the :terms and the :privacy', [
                            'terms' => '<a href="'.route('terms').'" target="_blank">'.e(__('Terms of use')).'</a>',
                            'privacy' => '<a href="'.route('privacy').'" target="_blank">'.e(__('Privacy policy')).'</a>',
                        ]) !!}
                    </label>
                    @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary w-100">{{ __('Register') }}</button>
            </form>

            <p class="text-center mt-4 mb-0 small">
                {{ __('Already registered?') }} <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
            </p>
        </div>
    </div>
@endsection
