@extends('layouts.admin')

@section('title', __('Visits'))

@php
    // Filters shared by the list and the calendar (not the dates).
    $filters = array_filter(request()->only(['time_slot', 'ticket_type', 'payment', 'search']), 'filled');
@endphp

@section('actions')
    <div class="btn-group me-1" role="group">
        <a href="{{ route('admin.visits.index', $filters) }}" @class(['btn', 'btn-secondary' => $view === 'list', 'btn-outline-secondary' => $view !== 'list'])><i class="bi bi-list-ul"></i> {{ __('List') }}</a>
        <a href="{{ route('admin.visits.index', $filters + ['view' => 'calendar', 'month' => $from->format('Y-m')]) }}" @class(['btn', 'btn-secondary' => $view === 'calendar', 'btn-outline-secondary' => $view !== 'calendar'])><i class="bi bi-calendar3"></i> {{ __('Calendar') }}</a>
    </div>
    @if ($view === 'list')
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv"></i> {{ __('Export CSV') }}</a>
    @endif
    @can('visits.create')
        <a href="{{ route('admin.entry') }}" class="btn btn-success"><i class="bi bi-door-open"></i> {{ __('New entrance') }}</a>
    @endcan
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="GET">
                @if ($view === 'calendar')
                    <input type="hidden" name="view" value="calendar">
                    <div class="col-12 col-md-4">
                        <label class="form-label small">{{ __('Month') }}</label>
                        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control">
                    </div>
                @else
                    <div class="col-6 col-md-2">
                        <label class="form-label small">{{ __('From') }}</label>
                        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small">{{ __('To') }}</label>
                        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
                    </div>
                @endif
                <div class="col-6 col-md-2">
                    <label class="form-label small">{{ __('Hours') }}</label>
                    <select name="time_slot" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($timeSlots as $slot)
                            <option value="{{ $slot->id }}" @selected(request('time_slot') == $slot->id)>{{ $slot->local_name }} ({{ $slot->label() }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">{{ __('Ticket') }}</label>
                    <select name="ticket_type" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($ticketTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('ticket_type') == $type->id)>{{ $type->local_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label small">{{ __('Payment') }}</label>
                    <select name="payment" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method }}" @selected(request('payment') === $method)>{{ __($method) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">{{ __('Visitor') }}</label>
                    <input name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Name or ID') }}">
                </div>
                <div class="col-12 col-md-1 d-grid">
                    <button class="btn btn-secondary"><i class="bi bi-funnel"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="info-box"><span class="info-box-icon text-bg-primary"><i class="bi bi-door-open"></i></span>
                <div class="info-box-content"><span class="info-box-text">{{ $view === 'calendar' ? __('Visits this month') : __('Visits') }}</span><span class="info-box-number">{{ $totals->visits }}</span></div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box"><span class="info-box-icon text-bg-success"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content"><span class="info-box-text">{{ __('Paid for entries') }}</span><span class="info-box-number">{{ number_format($totals->revenue, 2) }} ₾</span>
                    @if ($totals->rentals > 0)<small class="text-body-secondary">{{ __('incl. rental gear :amount ₾', ['amount' => number_format($totals->rentals, 2)]) }}</small>@endif
                </div></div>
        </div>
        <div class="col-md-6">
            <div class="d-flex flex-wrap gap-2 h-100 align-items-center">
                @foreach ($byPayment as $row)
                    <span class="badge text-bg-light border fs-6 fw-normal">{{ __($row->payment_method) }}: {{ $row->visits }} · {{ number_format($row->revenue, 2) }} ₾</span>
                @endforeach
            </div>
        </div>
    </div>

    @if ($view === 'calendar')
        <div class="card">
            <div class="card-body">
                @include('partials.calendar.month', [
                    'items' => $days,
                    'dayView' => 'partials.calendar.summary-day',
                    'dayData' => ['max' => $maxDay],
                    'monthUrl' => fn ($date) => route('admin.visits.index', $filters + ['view' => 'calendar', 'month' => $date->format('Y-m')]),
                    'dayUrl' => fn ($date) => route('admin.visits.index', $filters + ['from' => $date->toDateString(), 'to' => $date->toDateString()]),
                ])
                <p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-info-circle"></i> {{ __('Number of visits and money per day; darker = busier. Click a day to see its visits.') }}</p>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('Date') }}</th><th>{{ __('Visitor') }}</th><th class="text-center" title="{{ __('Visit number of this visitor') }}">#</th><th>{{ __('Age') }}</th><th>{{ __('Ticket') }}</th>
                        <th>{{ __('Hours') }}</th><th class="text-end">{{ __('Price') }}</th><th>{{ __('Staff') }}</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($visits as $visit)
                        <tr>
                            <td class="text-nowrap">{{ $visit->entered_at->format('d.m.Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.visitors.show', $visit->visitor_id) }}">{{ $visit->visitor?->full_name }}</a>
                                <small class="text-body-secondary d-block">{{ $visit->visitor?->personal_id }}</small>
                            </td>
                            <td class="text-center text-body-secondary small">{{ $visit->visit_number }}</td>
                            <td>{{ $visit->age_at_visit }} <small class="text-body-secondary">{{ $visit->ageGroup?->label() }}</small></td>
                            <td>{{ $visit->ticketType?->local_name }}</td>
                            <td>{{ $visit->timeSlot?->label() }}</td>
                            <td class="text-end" style="min-width: 160px">@include('admin.partials.visit-price')</td>
                            <td><small>{{ $visit->creator?->name }}</small></td>
                            <td class="text-end">
                                @can('visits.delete')
                                    <form method="POST" action="{{ route('admin.visits.destroy', $visit) }}" data-confirm="{{ __('Cancel this visit?') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger p-0" title="{{ __('Cancel visit') }}"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-body-secondary">{{ __('No visits in this period.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($visits->hasPages())
                <div class="card-footer">{{ $visits->links() }}</div>
            @endif
        </div>
    @endif
@endsection
