@extends('layouts.admin')

@section('title', __('Roles'))

@section('actions')
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-outline-secondary"><i class="bi bi-key"></i> {{ __('Permissions overview') }}</a>
    @can('roles.manage')
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> {{ __('New role') }}</a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>{{ __('Role') }}</th><th>{{ __('Code') }}</th><th class="text-center">{{ __('Users') }}</th><th class="text-center">{{ __('Permissions') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td>
                            <strong>{{ __($role->display_name ?? '') }}</strong>
                            @if ($role->is_system)<span class="badge text-bg-light border">{{ __('system') }}</span>@endif
                            <div class="small text-body-secondary">{{ __($role->description ?? '') }}</div>
                        </td>
                        <td><code>{{ $role->name }}</code></td>
                        <td class="text-center"><a href="{{ route('admin.users.index', ['role' => $role->name]) }}">{{ $role->users_count }}</a></td>
                        <td class="text-center">
                            {{ $role->name === config('permissions.super_admin_role') ? __('all') : $role->permissions_count }}
                        </td>
                        <td class="text-end text-nowrap">
                            @can('roles.manage')
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @unless ($role->is_system)
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" data-confirm="{{ __('Delete this role?') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
