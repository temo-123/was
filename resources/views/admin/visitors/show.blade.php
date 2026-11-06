@extends('layouts.admin')

@section('title', $visitor->full_name)

@section('actions')
    @can('visits.create')
        <a href="{{ route('admin.entry', ['visitor' => $visitor->id]) }}" class="btn btn-success"><i class="bi bi-door-open"></i> {{ __('Register entrance') }}</a>
    @endcan
    @can('visitors.update')
        <a href="{{ route('admin.visitors.edit', $visitor) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
    @endcan
    @can('visitors.delete')
        <form method="POST" action="{{ route('admin.visitors.destroy', $visitor) }}" class="d-inline" data-confirm="{{ __('Delete this visitor?') }}">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    @endcan
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Visitor card') }}</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('ID number') }}</dt><dd class="col-7">{{ $visitor->personal_id }}</dd>
                        <dt class="col-5">{{ __('Date of birth') }}</dt><dd class="col-7">{{ $visitor->birth_date->format('d.m.Y') }} ({{ $visitor->ageAt() }})</dd>
                        <dt class="col-5">{{ __('Phone') }}</dt><dd class="col-7"><a href="tel:{{ $visitor->phone }}">{{ $visitor->phone }}</a></dd>
                        <dt class="col-5">{{ __('Email') }}</dt><dd class="col-7">{{ $visitor->email ?: '—' }}</dd>
                        <dt class="col-5">{{ __('Category') }}</dt><dd class="col-7">{{ $visitor->category?->local_name ?? '—' }}</dd>
                        <dt class="col-5">{{ __('Client status') }}</dt>
                        <dd class="col-7">
                            @include('admin.partials.tier-badge')
                            @if (! $visitor->activeTier())
                                {{ $visitor->tier ? __(':status (expired)', ['status' => $visitor->tier->local_name]) : '—' }}
                            @elseif ($visitor->client_tier_until)
                                <small class="text-body-secondary d-block">{{ __('until :date', ['date' => $visitor->client_tier_until->format('d.m.Y')]) }}</small>
                            @endif
                        </dd>
                        <dt class="col-5">{{ __('Gym terms') }}</dt>
                        <dd class="col-7">
                            @if ($visitor->terms_accepted)
                                <span class="text-success"><i class="bi bi-check-circle"></i> {{ $visitor->terms_accepted_at?->format('d.m.Y') }}</span>
                            @else
                                <span class="badge text-bg-warning">{{ __('not accepted') }}</span>
                            @endif
                        </dd>
                        <dt class="col-5">{{ __('Registered') }}</dt><dd class="col-7">{{ $visitor->created_at->format('d.m.Y') }} <small class="text-body-secondary">{{ $visitor->creator?->name }}</small></dd>
                    </dl>
                    @if ($visitor->comment)
                        <div class="alert alert-warning mt-3 mb-0 small"><i class="bi bi-chat-left-text me-1"></i>{{ $visitor->comment }}</div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col"><div class="h4 mb-0">{{ $stats['visits'] }}</div><small class="text-body-secondary">{{ __('visits') }}</small></div>
                        <div class="col"><div class="h4 mb-0">{{ number_format($stats['spent'], 0) }} ₾</div><small class="text-body-secondary">{{ __('spent') }}</small></div>
                        <div class="col"><div class="h6 mb-0 mt-1">{{ $stats['last_visit'] ? \Illuminate\Support\Carbon::parse($stats['last_visit'])->format('d.m.Y') : '—' }}</div><small class="text-body-secondary">{{ __('last visit') }}</small></div>
                    </div>
                </div>
            </div>

            @if ($nextReward)
                {{-- Visit rewards: how far to the next one --}}
                @php
                    $every = $nextReward['reward']->every_visits;
                @endphp
                <div class="card mb-4 border-success-subtle">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold"><i class="bi bi-gift text-success"></i> {{ __('Next reward') }}</span>
                            <span class="badge text-bg-success">{{ trans_choice(':count visit left|:count visits left', $nextReward['in'], ['count' => $nextReward['in']]) }}</span>
                        </div>
                        <div class="progress mb-2" style="height: 10px" role="progressbar">
                            <div class="progress-bar bg-success" style="width: {{ round(($every - $nextReward['in']) / $every * 100) }}%"></div>
                        </div>
                        <div class="small text-body-secondary">
                            {{ $nextReward['reward']->describe() }} — {{ __('visit no. :number', ['number' => $stats['visits'] + $nextReward['in']]) }}
                        </div>
                    </div>
                </div>
            @endif

            @can('visitors.status')
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-star me-1"></i> {{ __('Client status') }}</h3></div>
                    <div class="card-body">
                        @if ($tiers->isEmpty())
                            <p class="small text-body-secondary mb-0">
                                {{ __('No statuses yet.') }}
                                @can('loyalty.manage')<a href="{{ route('admin.loyalty.index') }}">{{ __('Create VIP statuses') }}</a>@endcan
                            </p>
                        @else
                            <form method="POST" action="{{ route('admin.visitors.status', $visitor) }}" class="row g-2">
                                @csrf
                                <div class="col-7">
                                    <select name="client_tier_id" class="form-select form-select-sm">
                                        <option value="">{{ __('No status') }}</option>
                                        @foreach ($tiers as $tier)
                                            <option value="{{ $tier->id }}" @selected($visitor->client_tier_id === $tier->id)>
                                                {{ $tier->local_name }} {{ \App\Models\ClientTier::discountLabel((float) $tier->discount_percent) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-5">
                                    <input type="date" name="client_tier_until" value="{{ $visitor->client_tier_until?->toDateString() }}" min="{{ today()->toDateString() }}"
                                           class="form-control form-control-sm @error('client_tier_until') is-invalid @enderror" title="{{ __('Until (empty = no end)') }}">
                                </div>
                                <div class="col-12 d-flex align-items-center gap-2">
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> {{ __('Save') }}</button>
                                    <small class="text-body-secondary">{{ __('Date = until when; empty = no end.') }}</small>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan

            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-person-badge me-1"></i> {{ __('Online account') }}</h3></div>
                <div class="card-body">
                    @if ($visitor->user)
                        <p class="mb-1"><strong>{{ $visitor->user->name }}</strong></p>
                        <p class="mb-1 small">{{ $visitor->user->email }}</p>
                        <p class="small text-body-secondary">
                            {{ __('Linked via :via', ['via' => $visitor->userLink->linked_via]) }}
                            · {{ $visitor->userLink->created_at->format('d.m.Y') }}
                            @if ($visitor->userLink->linker) · {{ $visitor->userLink->linker->name }} @endif
                        </p>
                        @can('visitors.link')
                            <form method="POST" action="{{ route('admin.visitors.unlink', $visitor) }}" data-confirm="{{ __('Remove the link?') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Unlink') }}</button>
                            </form>
                        @endcan
                    @else
                        <p class="small text-body-secondary">{{ __('Not linked. It links automatically when the visitor registers with the same ID number or a verified email.') }}</p>
                        @can('visitors.link')
                            <form method="POST" action="{{ route('admin.visitors.link', $visitor) }}" class="input-group input-group-sm">
                                @csrf
                                <input type="email" name="email" class="form-control" placeholder="{{ __('User email') }}" required>
                                <button class="btn btn-outline-primary">{{ __('Link') }}</button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Subscriptions') }}</h3></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Hours') }}</th><th>{{ __('Valid') }}</th><th>{{ __('Used') }}</th><th>{{ __('Price') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($subscriptions as $sub)
                            <tr @class(['table-success' => $sub->isActive(), 'text-body-secondary' => ! $sub->isActive()])>
                                <td>{{ $sub->ticketType->local_name }}</td>
                                <td>{{ $sub->timeSlot->label() }}</td>
                                <td>{{ $sub->starts_on->format('d.m.Y') }} – {{ $sub->ends_on->format('d.m.Y') }}</td>
                                <td>{{ $sub->visits_used }}{{ $sub->visits_limit ? ' / '.$sub->visits_limit : '' }}</td>
                                <td>{{ number_format($sub->price, 2) }} ₾</td>
                                <td class="text-end">
                                    @if ($sub->cancelled_at)
                                        <span class="badge text-bg-secondary">{{ __('cancelled') }}</span>
                                    @elseif ($sub->isActive())
                                        @can('subscriptions.cancel')
                                            <form method="POST" action="{{ route('admin.subscriptions.cancel', $sub) }}" data-confirm="{{ __('Cancel this subscription?') }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-danger">{{ __('Cancel') }}</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-body-secondary">{{ __('No subscriptions.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @php
                $monthUrl = fn ($date) => route('admin.visitors.show', [$visitor, 'month' => $date->format('Y-m')]);
                $dayUrl = fn ($date) => route('admin.visitors.show', [$visitor, 'month' => $date->format('Y-m'), 'day' => $date->toDateString()]);
                $headings = '<thead><tr><th>'.e(__('Date')).'</th><th class="text-center">#</th><th>'.e(__('Age')).'</th><th>'.e(__('Ticket')).'</th><th>'
                    .e(__('Hours')).'</th><th class="text-end">'.e(__('Price')).'</th><th>'.e(__('Staff')).'</th><th></th></tr></thead>';
            @endphp
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto">{{ __('Visits') }}</h3>
                    <ul class="nav nav-pills nav-sm card-header-pills">
                        <li class="nav-item">
                            <a @class(['nav-link py-1', 'active' => $view === 'calendar']) href="{{ route('admin.visitors.show', [$visitor, 'month' => $month->format('Y-m')]) }}">
                                <i class="bi bi-calendar3"></i> {{ __('Calendar') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a @class(['nav-link py-1', 'active' => $view === 'list']) href="{{ route('admin.visitors.show', [$visitor, 'view' => 'list']) }}">
                                <i class="bi bi-list-ul"></i> {{ __('List') }}
                            </a>
                        </li>
                    </ul>
                </div>

                @if ($view === 'calendar')
                    <div class="card-body">
                        @include('partials.calendar.year-strip', ['counts' => $calendar['yearCounts']])
                        @include('partials.calendar.month', [
                            'items' => $calendar['items'],
                            'dayView' => 'partials.calendar.visitor-day',
                            'selected' => $selectedDay,
                        ])
                        <div class="d-flex flex-wrap gap-3 align-items-center small mt-3">
                            <span><b>{{ trans_choice(':count visit|:count visits', $calendar['monthVisits'], ['count' => $calendar['monthVisits']]) }}</b> {{ __('this month') }}</span>
                            <span><b>{{ number_format($calendar['monthSpent'], 2) }} ₾</b> {{ __('spent') }}</span>
                            <span class="ms-auto d-flex flex-wrap gap-2 text-body-secondary">
                                <span class="vcal-chip paid">{{ __('paid') }}</span>
                                <span class="vcal-chip subscription">{{ __('subscription') }}</span>
                                <span class="vcal-chip free">{{ __('free') }}</span>
                                <span><i class="bi bi-bag"></i> {{ __('gear') }}</span>
                                <span><i class="bi bi-gift"></i> {{ __('reward') }}</span>
                                <span><i class="bi bi-star-fill"></i> {{ __('status discount') }}</span>
                            </span>
                        </div>
                    </div>

                    {{-- The chosen day in detail --}}
                    @if ($selectedDay)
                        @php
                            $dayVisits = $calendar['items'][$selectedDay] ?? collect();
                        @endphp
                        <div class="card-header border-top bg-body-tertiary d-flex align-items-center">
                            <span class="fw-semibold me-auto"><i class="bi bi-calendar-event"></i> {{ \Illuminate\Support\Carbon::parse($selectedDay)->translatedFormat('j F Y') }}, {{ \Illuminate\Support\Carbon::parse($selectedDay)->translatedFormat('l') }}</span>
                            <a href="{{ $monthUrl($month) }}" class="btn btn-sm btn-link" title="{{ __('Close') }}"><i class="bi bi-x-lg"></i></a>
                        </div>
                        <div class="table-responsive">
                            <table class="table mb-0">
                                {!! $headings !!}
                                <tbody>
                                @forelse ($dayVisits as $visit)
                                    @include('admin.visitors._visit-row')
                                @empty
                                    <tr><td colspan="8" class="text-body-secondary">{{ __('No visits on this day.') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                @else
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            {!! $headings !!}
                            <tbody>
                            @forelse ($visits as $visit)
                                @include('admin.visitors._visit-row')
                            @empty
                                <tr><td colspan="8" class="text-body-secondary">{{ __('No visits yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($visits->hasPages())
                        <div class="card-footer">{{ $visits->appends(['view' => 'list'])->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
