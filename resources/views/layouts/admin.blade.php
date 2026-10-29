<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Admin')) · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/sass/admin.scss', 'resources/js/admin.js'])
    @include('partials.js-translations')
    @stack('head')
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a>
                </li>
                @can('visits.create')
                    <li class="nav-item d-none d-md-block">
                        <a href="{{ route('admin.entry') }}" class="nav-link"><i class="bi bi-door-open"></i> {{ __('New entrance') }}</a>
                    </li>
                @endcan
            </ul>
            <ul class="navbar-nav ms-auto">
                @include('partials.locale-switcher')
                <li class="nav-item d-none d-md-block">
                    <a href="{{ route('home') }}" class="nav-link" target="_blank" title="{{ __('Website') }}"><i class="bi bi-globe"></i></a>
                </li>
                <li class="nav-item dropdown user-menu">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                        <img src="{{ auth()->user()->avatarUrl() }}" class="user-image rounded-circle shadow" alt="">
                        <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                        <li class="user-header text-bg-primary">
                            <img src="{{ auth()->user()->avatarUrl() }}" class="rounded-circle shadow" alt="">
                            <p>
                                {{ auth()->user()->name }}
                                <small>{{ auth()->user()->roles->map(fn ($role) => __($role->display_name))->join(', ') }}</small>
                            </p>
                        </li>
                        <li class="user-footer">
                            <a href="{{ route('profile.show') }}" class="btn btn-default btn-flat">{{ __('Profile') }}</a>
                            <form method="POST" action="{{ route('logout') }}" class="d-inline float-end">
                                @csrf
                                <button class="btn btn-default btn-flat">{{ __('Sign out') }}</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="{{ route('admin.dashboard') }}" class="brand-link">
                <i class="bi bi-triangle-fill text-warning ms-2 me-2"></i>
                <span class="brand-text fw-light">{{ config('app.name') }}</span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                @include('admin.partials.menu')
            </nav>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <h3 class="mb-0">@yield('title')</h3>
                    </div>
                    <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
                        @yield('actions')
                    </div>
                </div>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                @include('partials.flash')
                @yield('content')
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">
            <a href="{{ route('terms') }}">{{ __('Terms') }}</a> · <a href="{{ route('privacy') }}">{{ __('Privacy') }}</a>
        </div>
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </footer>
</div>
@stack('scripts')
</body>
</html>
