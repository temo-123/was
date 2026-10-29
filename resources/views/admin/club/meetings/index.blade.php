@extends('layouts.admin')

@section('title', __('Club meetings'))

@section('actions')
    @can('club.meetings.manage')
        <a href="{{ route('admin.club.meetings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New meeting') }}</a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form class="row g-2 align-items-end" method="GET">
                <div class="col-md-4">
                    <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Title or location…') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label for="from" class="form-label small mb-1">{{ __('From') }}</label>
                    <input id="from" name="from" type="date" value="{{ request('from') }}" class="form-control @error('from') is-invalid @enderror">
                </div>
                <div class="col-6 col-md-2">
                    <label for="to" class="form-label small mb-1">{{ __('To') }}</label>
                    <input id="to" name="to" type="date" value="{{ request('to') }}" class="form-control @error('to') is-invalid @enderror">
                </div>
                <div class="col-md-2">
                    <label for="order" class="form-label small mb-1">{{ __('Order') }}</label>
                    <select id="order" name="order" class="form-select">
                        <option value="newest">{{ __('Newest first') }}</option>
                        <option value="oldest" @selected(request('order') === 'oldest')>{{ __('Oldest first') }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-secondary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    @if ($filtered)
                        <a href="{{ route('admin.club.meetings.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear') }}"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
                @if ($errors->any())
                    <div class="col-12 text-danger small">{{ $errors->first() }}</div>
                @endif
            </form>
        </div>
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
                @forelse ($months as $month => $items)
                    <tr class="table-light">
                        <th colspan="6" class="small text-uppercase text-body-secondary">
                            {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->translatedFormat('F Y') }}
                            <span class="badge text-bg-secondary ms-1">{{ $items->count() }}</span>
                        </th>
                    </tr>
                    @foreach ($items as $meeting)
                        <tr @class(['table-primary' => $meeting->starts_at->isToday()])>
                            <td class="text-nowrap">
                                {{ $meeting->starts_at->format('d.m.Y H:i') }}
                                <div class="small text-body-secondary">{{ $meeting->starts_at->translatedFormat('l') }}</div>
                            </td>
                            <td><a href="{{ route('admin.club.meetings.show', $meeting) }}" class="fw-semibold">{{ $meeting->title }}</a></td>
                            <td>{{ $meeting->location ?? '—' }}</td>
                            <td class="text-center">{{ $meeting->attendances_count }} <small class="text-body-secondary">/ {{ $activeMembers }}</small></td>
                            <td>
                                @if ($meeting->starts_at->isToday())
                                    <span class="badge text-bg-primary">{{ __('today') }}</span>
                                @elseif ($meeting->starts_at->isFuture())
                                    <span class="badge text-bg-info">{{ __('upcoming') }}</span>
                                @endif
                                @if ($meeting->is_open)
                                    <span class="badge text-bg-success">{{ __('open') }}</span>
                                @else
                                    <span class="badge text-bg-secondary"><i class="bi bi-lock"></i> {{ __('finished') }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @can('club.meetings.manage')
                                    <a href="{{ route('admin.club.meetings.screen', $meeting) }}" target="_blank" rel="noopener" class="btn btn-sm btn-dark" title="{{ __('Show QR code') }}"><i class="bi bi-qr-code"></i></a>
                                    <a href="{{ route('admin.club.meetings.edit', $meeting) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="6" class="text-body-secondary">{{ $filtered ? __('No meetings in this period.') : __('No meetings yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($meetings->hasPages())
            <div class="card-footer">{{ $meetings->links() }}</div>
        @endif
    </div>
@endsection
