@extends('layouts.app')

@section('title', __('Verify email'))

@section('content')
    <div class="card shadow-sm mx-auto auth-card">
        <div class="card-body p-4 text-center">
            <i class="bi bi-envelope-check display-4 text-primary"></i>
            <h1 class="h4 my-3">{{ __('Verify your email address') }}</h1>
            <p class="text-body-secondary">
                {{ __('We sent a verification link to :email. Click it to confirm your address.', ['email' => auth()->user()->email]) }}
            </p>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn btn-primary">{{ __('Send the link again') }}</button>
            </form>
        </div>
    </div>
@endsection
