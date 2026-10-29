@extends('layouts.admin')

@section('title', $user->exists ? $user->name : __('New user'))

@section('actions')
    @if ($user->exists && ! $user->is(auth()->user()))
        @can('users.delete')
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline" data-confirm="{{ __('Delete this user?') }}">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('Delete') }}</button>
            </form>
        @endcan
    @endif
@endsection

@section('content')
    @php
        $userRoleIds = collect(old('roles', $user->exists ? $user->roles->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
        $userPermIds = collect(old('permissions', $user->exists ? $user->permissions->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
        $isSelf = $user->is(auth()->user());
    @endphp

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <div class="row">
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">{{ __('Account') }}</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="name">{{ __('Name') }} *</label>
                            <input id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">{{ __('Email') }} *</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="phone">{{ __('Phone') }}</label>
                                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="personal_id">{{ __('ID number') }}</label>
                                <input id="personal_id" name="personal_id" inputmode="numeric" maxlength="11" pattern="\d{11}" data-digits="11" placeholder="01001012345" data-error="{{ __('Exactly 11 digits.') }}" value="{{ old('personal_id', $user->personal_id) }}" class="form-control @error('personal_id') is-invalid @enderror">
                                <div class="invalid-feedback">{{ $errors->first('personal_id') }}</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="password">{{ $user->exists ? __('New password') : __('Password') . ' *' }}</label>
                                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="password_confirmation">{{ __('Confirm password') }}</label>
                                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) @disabled($isSelf)>
                            <label class="form-check-label" for="is_active">{{ __('Active (can sign in)') }}</label>
                        </div>
                        @if ($user->exists)
                            <hr>
                            <dl class="row small mb-0">
                                <dt class="col-5">{{ __('Registered') }}</dt><dd class="col-7">{{ $user->created_at->format('d.m.Y H:i') }}</dd>
                                <dt class="col-5">{{ __('Last login') }}</dt><dd class="col-7">{{ $user->last_login_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                                <dt class="col-5">{{ __('Email verified') }}</dt><dd class="col-7">{{ $user->email_verified_at?->format('d.m.Y') ?? __('no') }}</dd>
                                <dt class="col-5">Google</dt><dd class="col-7">{{ $user->google_id ? __('connected') : '—' }}</dd>
                                <dt class="col-5">{{ __('Visitor card') }}</dt>
                                <dd class="col-7">
                                    @if ($user->visitor)
                                        <a href="{{ route('admin.visitors.show', $user->visitor) }}">{{ $user->visitor->full_name }}</a>
                                    @else — @endif
                                </dd>
                            </dl>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">{{ __('Roles') }}</h3></div>
                    <div class="card-body">
                        @foreach ($roles as $role)
                            @php $locked = $role->name === config('permissions.super_admin_role') && (! auth()->user()->isSuperAdmin() || $isSelf); @endphp
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role{{ $role->id }}"
                                       @checked(in_array($role->id, $userRoleIds, true)) @disabled($locked)>
                                <label class="form-check-label" for="role{{ $role->id }}">
                                    <strong>{{ __($role->display_name ?? '') }}</strong>
                                    <small class="text-body-secondary">{{ __($role->description ?? '') }}</small>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('Extra permissions') }}</h3>
                        <div class="small text-body-secondary">{{ __('Given to this user in addition to the permissions of their roles.') }}</div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($permissions as $group => $items)
                                <div class="col-md-6 mb-3">
                                    <div class="fw-semibold text-uppercase small text-body-secondary mb-1">{{ __($group) }}</div>
                                    @foreach ($items as $permission)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm{{ $permission->id }}"
                                                   @checked(in_array($permission->id, $userPermIds, true))>
                                            <label class="form-check-label small" for="perm{{ $permission->id }}" title="{{ $permission->name }}">{{ __($permission->display_name ?? '') }}</label>
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
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </form>
@endsection
