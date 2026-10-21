@extends('layouts.admin')

@section('title', __('Dashboard'))

@section('actions')
    @can('visits.create')
        <a href="{{ route('admin.entry') }}" class="btn btn-success"><i class="bi bi-door-open"></i> {{ __('New entrance') }}</a>
    @endcan
@endsection

@section('content')
    <div class="row">
        @foreach ([
            ['bi-door-open', 'text-bg-primary', __('Visits today'), $stats['visits_today']],
            ['bi-cash-stack', 'text-bg-success', __('Revenue today'), number_format($stats['revenue_today'], 2).' ₾'],
            ['bi-card-checklist', 'text-bg-info', __('Active subscriptions'), $stats['active_subscriptions'].' (+'.$stats['subscriptions_sold_today'].' '.__('today').')'],
            ['bi-people', 'text-bg-warning', __('Visitors'), $stats['visitors_total'].' (+'.$stats['new_visitors_today'].' '.__('today').')'],
        ] as [$icon, $color, $label, $value])
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="info-box">
                    <span class="info-box-icon {{ $color }} shadow-sm"><i class="bi {{ $icon }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $label }}</span>
                        <span class="info-box-number">{{ $value }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">{{ __('Today\'s entrances') }}</h3>
                    @can('visits.view')
                        <a href="{{ route('admin.visits.index') }}" class="btn btn-sm btn-outline-secondary ms-auto">{{ __('All visits') }}</a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Visitor') }}</th><th>{{ __('Ticket') }}</th><th class="text-end">{{ __('Price') }}</th></tr></thead>
                        <tbody>
                        @forelse ($latestVisits as $visit)
                            <tr>
                                <td>{{ $visit->entered_at->format('H:i') }}</td>
                                <td><a href="{{ route('admin.visitors.show', $visit->visitor_id) }}">{{ $visit->visitor?->full_name }}</a></td>
                                <td>{{ $visit->ticketType?->name }} <small class="text-body-secondary">{{ $visit->timeSlot?->label() }}</small></td>
                                <td class="text-end">@include('admin.partials.visit-price', ['visit' => $visit])</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-body-secondary">{{ __('No entrances yet today.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Last 7 days') }}</h3></div>
                <div class="card-body">
                    <div class="d-flex align-items-end gap-2 bar-chart" style="height: 160px">
                        @foreach ($days as $day)
                            <div class="flex-fill text-center d-flex flex-column justify-content-end h-100">
                                <small class="fw-semibold">{{ $day['visits'] }}</small>
                                <div class="bar" style="height: {{ round($day['visits'] / $maxDay * 120) }}px"></div>
                                <small class="text-body-secondary">{{ $day['date']->format('d.m') }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Today by time of day') }}</h3></div>
                <ul class="list-group list-group-flush">
                    @foreach ($bySlot as $slot)
                        <li @class(['list-group-item d-flex justify-content-between', 'active' => $currentSlot?->id === $slot->id])>
                            <span>{{ $slot->name }} <small>({{ $slot->label() }})</small></span>
                            <strong>{{ $slot->visits_count }}</strong>
                        </li>
                    @endforeach
                </ul>
                @unless ($currentSlot)
                    <div class="card-footer small text-body-secondary">{{ __('The gym is closed right now (no active time slot).') }}</div>
                @endunless
            </div>

            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Subscriptions ending soon') }}</h3></div>
                <ul class="list-group list-group-flush">
                    @forelse ($expiringSoon as $sub)
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="{{ route('admin.visitors.show', $sub->visitor_id) }}">{{ $sub->visitor?->full_name }}</a>
                            <span class="text-body-secondary">{{ $sub->ends_on->format('d.m') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('Nothing ends in the next 3 days.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
