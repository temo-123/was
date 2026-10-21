@extends('layouts.app')

@section('hero')
    <section class="hero py-5">
        <div class="container py-4">
            <h1 class="display-5 fw-bold">{{ config('app.name') }}</h1>
            <p class="lead col-lg-7">{{ __('Bouldering and climbing for everyone from 4 years old. Come alone, with friends or with the whole family.') }}</p>
            @guest
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg me-2">{{ __('Create an account') }}</a>
                <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg">{{ __('Sign in') }}</a>
            @else
                <a href="{{ route('profile.show') }}" class="btn btn-primary btn-lg">{{ __('My profile') }}</a>
            @endguest
        </div>
    </section>
@endsection

@section('content')
    <section id="prices" class="mb-5">
        <h2 class="h3 mb-3">{{ __('Prices') }}</h2>
        @if ($prices->isEmpty())
            <p class="text-body-secondary">{{ __('The price list will be published soon.') }}</p>
        @else
            @include('partials.price-table')
            <p class="small text-body-secondary mt-2">
                {{ __('Prices in GEL. The price depends on your age and on the time you arrive. A subscription is valid for :days days.', ['days' => $ticketTypes->firstWhere('duration_days', '!=', null)?->duration_days ?? 30]) }}
            </p>
        @endif
    </section>

    <section class="row g-4">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm"><div class="card-body">
                <h3 class="h5"><i class="bi bi-shield-check text-primary"></i> {{ __('Safety first') }}</h3>
                <p class="mb-0 text-body-secondary">{{ __('Every visitor reads and accepts the gym rules before the first climb.') }} <a href="{{ route('terms') }}">{{ __('Read the rules') }}</a></p>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm"><div class="card-body">
                <h3 class="h5"><i class="bi bi-person-vcard text-primary"></i> {{ __('Your gym card') }}</h3>
                <p class="mb-0 text-body-secondary">{{ __('Register with the same ID number you gave at the reception to see your visits and subscriptions online.') }}</p>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm"><div class="card-body">
                <h3 class="h5"><i class="bi bi-clock text-primary"></i> {{ __('Opening hours') }}</h3>
                <p class="mb-0 text-body-secondary">
                    @php $open = \App\Models\TimeSlot::where('is_active', true)->min('start_time'); $close = \App\Models\TimeSlot::where('is_active', true)->max('end_time'); @endphp
                    {{ $open ? substr($open, 0, 5).' – '.substr($close, 0, 5) : '—' }}
                </p>
            </div></div>
        </div>
    </section>
@endsection
