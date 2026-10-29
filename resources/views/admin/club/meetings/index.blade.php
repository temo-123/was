@extends('layouts.admin')

@section('title', __('Club meetings'))

@section('actions')
    @can('club.meetings.manage')
        <a href="{{ route('admin.club.meetings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New meeting') }}</a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Meeting') }}</th>
                    <th>{{ __('Location') }}</th>
                    <th class="text-center">{{ __('Attended') }}</th>
                    <th>{{ __('Check-in') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($meetings as $meeting)
                    <tr>
                        <td class="text-nowrap">{{ $meeting->starts_at->format('d.m.Y H:i') }}</td>
                        <td><a href="{{ route('admin.club.meetings.show', $meeting) }}" class="fw-semibold">{{ $meeting->title }}</a></td>
                        <td>{{ $meeting->location ?? '—' }}</td>
                        <td class="text-center">{{ $meeting->attendances_count }} <small class="text-body-secondary">/ {{ $activeMembers }}</small></td>
                        <td>
                            @if ($meeting->is_open)
                                <span class="badge text-bg-success">{{ __('open') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('closed') }}</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @can('club.meetings.manage')
                                <a href="{{ route('admin.club.meetings.screen', $meeting) }}" target="_blank" rel="noopener" class="btn btn-sm btn-dark" title="{{ __('Show QR code') }}"><i class="bi bi-qr-code"></i></a>
                                <a href="{{ route('admin.club.meetings.edit', $meeting) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-body-secondary">{{ __('No meetings yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($meetings->hasPages())
            <div class="card-footer">{{ $meetings->links() }}</div>
        @endif
    </div>
@endsection
