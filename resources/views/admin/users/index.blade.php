@extends('layouts.admin')

@section('title', __('Users'))

@section('actions')
    @can('users.create')
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> {{ __('New user') }}</a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form class="row g-2" method="GET">
                <div class="col-md-6"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Name, email, phone…') }}"></div>
                <div class="col-md-4">
                    <select name="role" class="form-select">
                        <option value="">{{ __('All roles') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-grid"><button class="btn btn-secondary"><i class="bi bi-search"></i></button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Visitor card') }}</th><th>{{ __('Last login') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($users as $user)
                    <tr @class(['text-body-secondary' => ! $user->is_active])>
                        <td>
                            <img src="{{ $user->avatarUrl() }}" class="rounded-circle me-1" width="28" height="28" alt="">
                            {{ $user->name }}
                            @unless ($user->is_active)<span class="badge text-bg-secondary">{{ __('disabled') }}</span>@endunless
                            @if ($user->google_id)<i class="bi bi-google text-body-secondary" title="Google"></i>@endif
                        </td>
                        <td>
                            {{ $user->email }}
                            @unless ($user->hasVerifiedEmail())<i class="bi bi-exclamation-circle text-warning" title="{{ __('Email not verified') }}"></i>@endunless
                        </td>
                        <td>@foreach ($user->roles as $role)<span class="badge text-bg-primary me-1">{{ $role->display_name }}</span>@endforeach</td>
                        <td>
                            @if ($user->visitor)
                                <a href="{{ route('admin.visitors.show', $user->visitor) }}">{{ $user->visitor->full_name }}</a>
                            @else — @endif
                        </td>
                        <td><small>{{ $user->last_login_at?->format('d.m.Y H:i') ?? '—' }}</small></td>
                        <td class="text-end">
                            @can('users.update')
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-body-secondary">{{ __('No users found.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-footer">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
