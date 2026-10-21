@extends('layouts.admin')

@section('title', $role->exists ? __('Edit role') : __('New role'))

@section('content')
    @php $selected = collect(old('permissions', $role->exists ? $role->permissions->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all(); @endphp

    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
        @csrf
        @if ($role->exists) @method('PUT') @endif
        <div class="row">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="display_name">{{ __('Name') }} *</label>
                            <input id="display_name" name="display_name" value="{{ old('display_name', $role->display_name) }}" class="form-control @error('display_name') is-invalid @enderror" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="name">{{ __('Code') }} *</label>
                            <input id="name" name="name" value="{{ old('name', $role->name) }}" class="form-control @error('name') is-invalid @enderror" required @readonly($role->is_system)>
                            <div class="form-text">{{ __('Latin letters, numbers and dashes, e.g. "coach".') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="description">{{ __('Description') }}</label>
                            <textarea id="description" name="description" rows="3" class="form-control">{{ old('description', $role->description) }}</textarea>
                        </div>
                        @if ($role->name === config('permissions.super_admin_role'))
                            <div class="alert alert-info small mb-0">{{ __('This role always has every permission, whatever is ticked here.') }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">{{ __('Permissions') }}</h3></div>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($permissions as $group => $items)
                                <div class="col-md-6 mb-3">
                                    <div class="fw-semibold text-uppercase small text-body-secondary mb-1">{{ $group }}</div>
                                    @foreach ($items as $permission)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm{{ $permission->id }}"
                                                   @checked(in_array($permission->id, $selected, true))>
                                            <label class="form-check-label" for="perm{{ $permission->id }}">
                                                {{ $permission->display_name }} <code class="small">{{ $permission->name }}</code>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </form>
@endsection
