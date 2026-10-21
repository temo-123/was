@extends('layouts.admin')

@section('title', __('Visits'))

@section('actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv"></i> {{ __('Export CSV') }}</a>
    @can('visits.create')
        <a href="{{ route('admin.entry') }}" class="btn btn-success"><i class="bi bi-door-open"></i> {{ __('New entrance') }}</a>
    @endcan
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="GET">
                <div class="col-6 col-md-2">
                    <label class="form-label small">{{ __('From') }}</label>
                    <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">{{ __('To') }}</label>
                    <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">{{ __('Hours') }}</label>
                    <select name="time_slot" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($timeSlots as $slot)
                            <option value="{{ $slot->id }}" @selected(request('time_slot') == $slot->id)>{{ $slot->name }} ({{ $slot->label() }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">{{ __('Ticket') }}</label>
                    <select name="ticket_type" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($ticketTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('ticket_type') == $type->id)>{{ $type->name }}</option>
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
                <div class="info-box-content"><span class="info-box-text">{{ __('Visits') }}</span><span class="info-box-number">{{ $totals->visits }}</span></div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box"><span class="info-box-icon text-bg-success"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content"><span class="info-box-text">{{ __('Paid for entries') }}</span><span class="info-box-number">{{ number_format($totals->revenue, 2) }} ₾</span></div></div>
        </div>
        <div class="col-md-6">
            <div class="d-flex flex-wrap gap-2 h-100 align-items-center">
                @foreach ($byPayment as $row)
                    <span class="badge text-bg-light border fs-6 fw-normal">{{ __($row->payment_method) }}: {{ $row->visits }} · {{ number_format($row->revenue, 2) }} ₾</span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                <tr>
                    <th>{{ __('Date') }}</th><th>{{ __('Visitor') }}</th><th>{{ __('Age') }}</th><th>{{ __('Ticket') }}</th>
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
                        <td>{{ $visit->age_at_visit }} <small class="text-body-secondary">{{ $visit->ageGroup?->label() }}</small></td>
                        <td>{{ $visit->ticketType?->name }}</td>
                        <td>{{ $visit->timeSlot?->label() }}</td>
                        <td class="text-end text-nowrap">@include('admin.partials.visit-price')</td>
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
                    <tr><td colspan="8" class="text-body-secondary">{{ __('No visits in this period.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($visits->hasPages())
            <div class="card-footer">{{ $visits->links() }}</div>
        @endif
    </div>
@endsection
