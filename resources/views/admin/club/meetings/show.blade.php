@extends('layouts.admin')

@section('title', $meeting->title)

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        @can('club.meetings.manage')
            <a href="{{ route('admin.club.meetings.screen', $meeting) }}" target="_blank" rel="noopener" class="btn btn-dark"><i class="bi bi-qr-code"></i> {{ __('Show QR code') }}</a>
            <form method="POST" action="{{ route('admin.club.meetings.toggle', $meeting) }}">
                @csrf
                <button @class(['btn', 'btn-outline-danger' => $meeting->is_open, 'btn-outline-success' => ! $meeting->is_open])>
                    <i class="bi {{ $meeting->is_open ? 'bi-lock' : 'bi-unlock' }}"></i> {{ $meeting->is_open ? __('Close check-in') : __('Open check-in') }}
                </button>
            </form>
            <a href="{{ route('admin.club.meetings.edit', $meeting) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
        @endcan
        <a href="{{ route('admin.club.meetings.export', $meeting) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel"></i> {{ __('Export') }}</a>
    </div>
@endsection

@section('content')
    <p class="text-body-secondary">
        <i class="bi bi-calendar-event"></i> {{ $meeting->starts_at->format('d.m.Y H:i') }}
        @if ($meeting->location) · <i class="bi bi-geo-alt"></i> {{ $meeting->location }} @endif
        · {{ $meeting->is_open ? __('check-in open') : __('check-in closed') }}
    </p>
    @php($newcomers = $attendances->filter(fn ($a) => $a->member->is_pending)->count())
    @if ($newcomers)
        <div class="alert alert-warning py-2">
            <i class="bi bi-person-plus"></i> {{ trans_choice(':count person registered themselves at check-in.|:count people registered themselves at check-in.', $newcomers) }}
            @can('club.members.manage')
                <a href="{{ route('admin.club.members.index', ['tab' => 'new']) }}">{{ __('Review them') }}</a>
            @endcan
        </div>
    @endif
    @if ($meeting->notes)<p>{!! nl2br(e($meeting->notes)) !!}</p>@endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">{{ __('Attended') }}: {{ $attendances->count() }}</h3></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>#</th><th>{{ __('Name') }}</th><th>{{ __('Time') }}</th><th>{{ __('How') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($attendances as $attendance)
                            <tr>
                                <td class="text-body-secondary">{{ $loop->iteration }}</td>
                                <td>
                                    {{ $attendance->member->full_name }}
                                    @if ($attendance->member->is_pending)
                                        <a href="{{ route('admin.club.members.edit', $attendance->member) }}" class="badge text-bg-warning text-decoration-none" title="{{ __('Registered themselves, not in the member list yet') }}">{{ __('new') }}</a>
                                    @endif
                                </td>
                                <td>{{ $attendance->checked_in_at->format('H:i') }}</td>
                                <td>
                                    @if ($attendance->method === 'qr')
                                        <span class="badge text-bg-primary"><i class="bi bi-qr-code"></i> QR</span>
                                    @else
                                        <span class="badge text-bg-secondary" title="{{ $attendance->creator?->name }}">{{ __('manual') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('club.meetings.manage')
                                        <form method="POST" action="{{ route('admin.club.meetings.attendances.destroy', [$meeting, $attendance]) }}" onsubmit="return confirm(@js(__('Remove from the list?')))">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger p-0" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-body-secondary">{{ __('Nobody has checked in yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @can('club.meetings.manage')
            <div class="col-lg-5">
                <form method="POST" action="{{ route('admin.club.meetings.attendances.store', $meeting) }}" class="card">
                    @csrf
                    <div class="card-header"><h3 class="card-title mb-0">{{ __('Mark manually') }} <small class="text-body-secondary">({{ __('not here yet') }}: {{ $absent->count() }})</small></h3></div>
                    <div class="card-body">
                        <input type="search" class="form-control form-control-sm mb-2" placeholder="{{ __('Filter…') }}" id="absent-filter">
                        <div style="max-height: 50vh; overflow-y: auto" id="absent-list">
                            @forelse ($absent as $member)
                                <div class="form-check" data-name="{{ mb_strtolower($member->full_name.' '.$member->phone.' '.$member->personal_id) }}">
                                    <input class="form-check-input" type="checkbox" name="member_ids[]" value="{{ $member->id }}" id="m{{ $member->id }}">
                                    <label class="form-check-label" for="m{{ $member->id }}">{{ $member->full_name }}</label>
                                </div>
                            @empty
                                <p class="text-body-secondary mb-0">{{ __('All active members are here.') }}</p>
                            @endforelse
                        </div>
                    </div>
                    @if ($absent->isNotEmpty())
                        <div class="card-footer"><button class="btn btn-primary btn-sm">{{ __('Mark as present') }}</button></div>
                    @endif
                </form>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('absent-filter')?.addEventListener('input', (e) => {
            const term = e.target.value.trim().toLowerCase();
            document.querySelectorAll('#absent-list [data-name]').forEach((row) => {
                row.hidden = term !== '' && !row.dataset.name.includes(term);
            });
        });
    </script>
@endpush
