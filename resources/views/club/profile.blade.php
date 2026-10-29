@extends('layouts.app')

@section('title', __('Complete your details'))

@section('content')
    <div class="mx-auto" style="max-width: 480px">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="text-center">
                    <i class="bi bi-person-vcard display-5 text-primary"></i>
                    <h1 class="h4 mt-2 mb-0">{{ $meeting->title }}</h1>
                    <p class="text-body-secondary">{{ $meeting->starts_at->format('d.m.Y H:i') }} @if ($meeting->location) · {{ $meeting->location }} @endif</p>
                </div>

                @if (session('checked_in'))
                    <div class="alert alert-success text-center"><i class="bi bi-check-circle-fill"></i> {{ session('checked_in') }}</div>
                @endif

                <form method="POST" action="{{ route('club.checkin.profile.update', $meeting) }}" class="p-3 bg-body-tertiary rounded" novalidate data-validate>
                    @csrf
                    <p class="fw-semibold mb-1">{{ __(':name, some of your details are missing.', ['name' => $member->first_name]) }}</p>
                    <p class="small text-body-secondary mb-2">{{ __('Please fill them in — it takes a minute and you won\'t be asked again.') }}</p>
                    @include('club._profile-fields', ['only' => $missing, 'values' => []])
                    <button class="btn btn-primary btn-lg w-100 mt-3"><i class="bi bi-floppy"></i> {{ __('Save my details') }}</button>
                </form>

                <p class="text-center mt-3 mb-0 small">
                    <a href="{{ route('club.checkin', $meeting) }}" class="link-secondary">{{ __('Later') }}</a>
                </p>
            </div>
        </div>
    </div>
@endsection
