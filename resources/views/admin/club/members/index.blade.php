@extends('layouts.admin')

@section('title', __('Club members'))

@section('actions')
    @can('club.members.manage')
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.club.import.create') }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-arrow-up"></i> {{ __('Import from Excel') }}</a>
            <a href="{{ route('admin.club.members.export', request()->only('search', 'status', 'tab')) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel"></i> {{ __('Export') }}</a>
            <a href="{{ route('admin.club.members.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> {{ __('New member') }}</a>
        </div>
    @endcan
@endsection

@section('content')
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a href="{{ route('admin.club.members.index') }}" @class(['nav-link', 'active' => ! $pendingTab])>
                <i class="bi bi-people"></i> {{ __('Members') }} <span class="badge text-bg-secondary">{{ $counts[0] ?? 0 }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.club.members.index', ['tab' => 'new']) }}" @class(['nav-link', 'active' => $pendingTab])>
                <i class="bi bi-person-plus"></i> {{ __('Self-registered') }}
                <span @class(['badge', 'text-bg-warning' => $counts[1] ?? 0, 'text-bg-secondary' => ! ($counts[1] ?? 0)])>{{ $counts[1] ?? 0 }}</span>
            </a>
        </li>
    </ul>
    <div class="card border-top-0 rounded-top-0">
        @if ($pendingTab)
            <div class="alert alert-info rounded-0 border-0 border-bottom mb-0 small">
                <i class="bi bi-info-circle"></i>
                {{ __('People who checked in at a meeting with "I\'m not registered yet". Add them to the member list, or merge them into an existing member if they are already there.') }}
            </div>
        @endif
        <div class="card-header">
            <form class="row g-2" method="GET">
                @if ($pendingTab)<input type="hidden" name="tab" value="new">@endif
                <div class="col-md-7">
                    <input name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Name, surname, ID number, phone, email…') }}" autofocus>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">{{ __('All members') }}</option>
                        <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-secondary"><i class="bi bi-search"></i> {{ __('Search') }}</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('ID number') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Email') }}</th>
                    @if ($pendingTab)
                        <th>{{ __('Registered') }}</th>
                        <th class="text-center">{{ __('Meetings') }}</th>
                        <th>{{ __('Maybe the same as') }}</th>
                    @else
                        <th>{{ __('Member since') }}</th>
                        <th class="text-center">{{ __('Meetings') }}</th>
                        <th>{{ __('Last meeting') }}</th>
                    @endif
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($members as $member)
                    <tr @class(['text-body-secondary' => ! $member->is_active])>
                        <td>
                            <span class="fw-semibold">{{ $member->full_name }}</span>
                            @unless ($member->is_active)<span class="badge text-bg-secondary">{{ __('inactive') }}</span>@endunless
                        </td>
                        <td class="font-monospace">{{ $member->personal_id ?? '—' }}</td>
                        <td class="font-monospace">{{ $member->phone ?? '—' }}</td>
                        <td>{{ $member->email ?? '—' }}</td>
                        @if ($pendingTab)
                            <td>{{ $member->created_at->format('d.m.Y H:i') }}</td>
                            <td class="text-center">{{ $member->attendances_count }}</td>
                            <td>
                                @if ($match = $suggestions[$member->id] ?? null)
                                    @can('club.members.manage')
                                        <form method="POST" action="{{ route('admin.club.members.merge', $member) }}" class="d-inline"
                                              onsubmit="return confirm(@js(__('Merge :from into :to?', ['from' => $member->full_name, 'to' => $match->full_name])))">
                                            @csrf
                                            <input type="hidden" name="target_id" value="{{ $match->id }}">
                                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-intersect"></i> {{ __('Merge into :name', ['name' => $match->full_name]) }}</button>
                                        </form>
                                    @else
                                        {{ $match->full_name }}
                                    @endif
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                        @else
                            <td>{{ $member->joined_on?->format('d.m.Y') ?? '—' }}</td>
                            <td class="text-center">{{ $member->attendances_count }}</td>
                            <td>{{ $member->attendances_max_checked_in_at ? \Illuminate\Support\Carbon::parse($member->attendances_max_checked_in_at)->format('d.m.Y') : '—' }}</td>
                        @endif
                        <td class="text-end text-nowrap">
                            @can('club.members.manage')
                                @if ($member->is_pending)
                                    <form method="POST" action="{{ route('admin.club.members.approve', $member) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success" title="{{ __('Add to the member list') }}"><i class="bi bi-person-check"></i> {{ __('Add to list') }}</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.club.members.edit', $member) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-body-secondary">{{ $pendingTab ? __('Nobody is waiting for approval.') : __('No club members found.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-body-secondary">{{ trans_choice(':count member|:count members', $members->total()) }}</small>
            {{ $members->links() }}
        </div>
    </div>
@endsection
