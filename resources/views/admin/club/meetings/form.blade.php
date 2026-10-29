@extends('layouts.admin')

@section('title', $meeting->exists ? __('Edit meeting') : __('New meeting'))

@section('content')
    <form method="POST" action="{{ $meeting->exists ? route('admin.club.meetings.update', $meeting) : route('admin.club.meetings.store') }}" class="card" novalidate data-validate>
        @csrf
        @if ($meeting->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="title" class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input id="title" name="title" required maxlength="255" value="{{ old('title', $meeting->title) }}" placeholder="{{ __('e.g. Monthly club meeting') }}"
                           class="form-control @error('title') is-invalid @enderror">
                    <div class="invalid-feedback">{{ $errors->first('title') }}</div>
                </div>
                <div class="col-md-4">
                    <label for="starts_at" class="form-label">{{ __('Date and time') }} <span class="text-danger">*</span></label>
                    <input id="starts_at" name="starts_at" type="datetime-local" required value="{{ old('starts_at', $meeting->starts_at?->format('Y-m-d\TH:i')) }}"
                           class="form-control @error('starts_at') is-invalid @enderror">
                    <div class="invalid-feedback">{{ $errors->first('starts_at') }}</div>
                </div>
                <div class="col-md-8">
                    <label for="location" class="form-label">{{ __('Location') }}</label>
                    <input id="location" name="location" maxlength="255" value="{{ old('location', $meeting->location) }}" class="form-control @error('location') is-invalid @enderror">
                </div>
                <div class="col-12">
                    <div class="fw-semibold"><i class="bi bi-wifi"></i> {{ __('Wi-Fi for the phones') }}</div>
                    <div class="form-text mt-0">
                        @if ($default = config('club.wifi.name'))
                            {{ __('Shown on the QR screen. Leave empty to use the default network: :name.', ['name' => $default]) }}
                        @else
                            {{ __('Shown on the QR screen with a code that connects the phone. Leave empty to show no Wi-Fi (a default can be set with CLUB_WIFI_NAME in .env).') }}
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="wifi_name" class="form-label">{{ __('Wi-Fi name') }}</label>
                    <input id="wifi_name" name="wifi_name" maxlength="32" autocomplete="off" value="{{ old('wifi_name', $meeting->wifi_name) }}"
                           placeholder="{{ config('club.wifi.name') }}" class="form-control @error('wifi_name') is-invalid @enderror">
                    <div class="invalid-feedback">{{ $errors->first('wifi_name') }}</div>
                </div>
                <div class="col-md-6">
                    <label for="wifi_password" class="form-label">{{ __('Wi-Fi password') }} <small class="text-body-secondary">({{ __('empty = open network') }})</small></label>
                    <input id="wifi_password" name="wifi_password" maxlength="63" autocomplete="off" value="{{ old('wifi_password', $meeting->wifi_password) }}"
                           class="form-control font-monospace @error('wifi_password') is-invalid @enderror">
                    <div class="invalid-feedback">{{ $errors->first('wifi_password') }}</div>
                </div>
                <div class="col-12">
                    <label for="notes" class="form-label">{{ __('Notes') }}</label>
                    <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $meeting->notes) }}</textarea>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $meeting->exists ? route('admin.club.meetings.show', $meeting) : route('admin.club.meetings.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            @if ($meeting->exists)
                <button form="delete-meeting" class="btn btn-outline-danger ms-auto" onclick="return confirm(@js(__('Delete this meeting and its attendance list?')))">
                    <i class="bi bi-trash"></i> {{ __('Delete') }}
                </button>
            @endif
        </div>
    </form>
    @if ($meeting->exists)
        <form id="delete-meeting" method="POST" action="{{ route('admin.club.meetings.destroy', $meeting) }}">@csrf @method('DELETE')</form>
    @endif
@endsection
