<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', __('Climbing gym'))">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-md bg-dark" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('home') }}">
            <i class="bi bi-triangle-fill text-primary"></i> {{ config('app.name') }}
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#prices">{{ __('Prices') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('terms') }}">{{ __('Gym rules') }}</a></li>
            </ul>
            <ul class="navbar-nav">
                @include('partials.locale-switcher')
                @auth
                    @if (auth()->user()->canAccessAdmin())
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i> {{ __('Admin') }}</a></li>
                    @endif
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="{{ auth()->user()->avatarUrl() }}" class="rounded-circle me-1" width="24" height="24" alt=""> {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('profile.show') }}">{{ __('My profile') }}</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item">{{ __('Sign out') }}</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">{{ __('Sign in') }}</a></li>
                    <li class="nav-item"><a class="btn btn-primary btn-sm ms-md-2 mt-1" href="{{ route('register') }}">{{ __('Register') }}</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<main>
    @hasSection('hero')
        @yield('hero')
    @endif
    <div class="container py-4">
        @include('partials.flash')
        @yield('content')
    </div>
</main>

<footer class="bg-dark text-white-50 py-4 mt-4" data-bs-theme="dark">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
        <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
        <span>
            <a class="link-light" href="{{ route('terms') }}">{{ __('Terms of use') }}</a> ·
            <a class="link-light" href="{{ route('privacy') }}">{{ __('Privacy policy') }}</a>
        </span>
    </div>
</footer>
@stack('scripts')
</body>
</html>
