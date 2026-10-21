@extends('layouts.admin')

@section('title', __('Permissions'))

@section('content')
    <div class="callout callout-info mb-3 small">
        {!! __('Permissions are defined in <code>config/permissions.php</code>. After adding one there, run <code>php artisan permissions:sync</code>. Give them to roles on the role page, or to a single user on the user page.') !!}
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>{{ __('Permission') }}</th>
                    @foreach ($roles as $role)
                        <th class="text-center small">{{ $role->display_name }}</th>
                    @endforeach
                    <th class="text-center small">{{ __('Users directly') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($permissions as $group => $items)
                    <tr class="table-secondary"><th colspan="{{ $roles->count() + 2 }}" class="text-uppercase small">{{ $group }}</th></tr>
                    @foreach ($items as $permission)
                        <tr>
                            <td>{{ $permission->display_name }} <code class="small">{{ $permission->name }}</code></td>
                            @foreach ($roles as $role)
                                <td class="text-center">
                                    @if ($role->name === config('permissions.super_admin_role') || $role->permissions->contains('id', $permission->id))
                                        <i class="bi bi-check-lg text-success"></i>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-center">{{ $permission->users_count ?: '' }}</td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
