@extends('layouts.admin')

@section('title', __('Price list'))

@section('actions')
    @can('pricing.manage')
        <a href="{{ route('admin.pricing.settings') }}" class="btn btn-outline-secondary"><i class="bi bi-sliders"></i> {{ __('Age groups & hours') }}</a>
    @endcan
@endsection

@section('content')
    @php $canEdit = auth()->user()->can('pricing.manage'); @endphp

    <div class="callout callout-info mb-3">
        {!! __('Final price = <strong>price from the table</strong> × <strong>all active coefficients that match</strong> (age group, hours, ticket, category, weekday, dates). Empty cell = this ticket is not sold for that group/time.') !!}
    </div>

    <form method="POST" action="{{ route('admin.pricing.update') }}" class="card mb-4">
        @csrf @method('PUT')
        <div class="card-header"><h3 class="card-title">{{ __('Base prices (₾)') }}</h3></div>
        <div class="table-responsive">
            <table class="table table-bordered mb-0 price-matrix text-center align-middle">
                <thead class="table-light">
                <tr>
                    <th rowspan="2">{{ __('Hours') }}</th>
                    <th rowspan="2">{{ __('Ticket') }}</th>
                    @foreach ($ageGroups as $group)
                        @php $span = $columns->filter(fn ($c) => $c['group']->id === $group->id)->count(); @endphp
                        <th colspan="{{ $span }}">{{ $group->name }} <small class="text-body-secondary">({{ $group->label() }})</small></th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($columns as $col)
                        <th class="small fw-normal">{{ $col['category']?->name ?? __('everyone') }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($timeSlots as $slot)
                    @foreach ($ticketTypes as $ticket)
                        <tr>
                            @if ($loop->first)
                                <th rowspan="{{ $ticketTypes->count() }}" class="text-nowrap">{{ $slot->name }}<br><small class="text-body-secondary">{{ $slot->label() }}</small></th>
                            @endif
                            <th class="text-start small">{{ $ticket->name }}</th>
                            @foreach ($columns as $col)
                                @php
                                    $key = "{$slot->id}-{$ticket->id}-{$col['group']->id}-".($col['category']?->id ?? 0);
                                    $rule = $prices[$key] ?? null;
                                @endphp
                                <td @class(['not-available' => ! $rule])>
                                    @if ($canEdit)
                                        <input type="number" step="0.01" min="0" name="prices[{{ $key }}]" value="{{ $rule ? (float) $rule->price : '' }}"
                                               class="form-control form-control-sm mx-auto" placeholder="—" style="max-width: 110px">
                                    @else
                                        {{ $rule ? number_format($rule->price, 2).' ₾' : '—' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($canEdit)
            <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
                <button class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save prices') }}</button>
                <span class="small text-body-secondary">{{ __('Clear a cell to stop selling that ticket for that group/time.') }}</span>
            </div>
        @endif
    </form>

    @if ($canEdit)
        <form method="POST" action="{{ route('admin.pricing.columns') }}" class="card card-body mb-4">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-12"><strong>{{ __('Separate prices for a category') }}</strong>
                    <div class="small text-body-secondary">{{ __('E.g. members pay less in the 16+ group. Adds a column for that category, copied from the current prices.') }}</div>
                </div>
                <div class="col-md-4">
                    <select name="age_group_id" class="form-select">
                        @foreach ($ageGroups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="visitor_category_id" class="form-select">
                        @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4 d-grid"><button class="btn btn-outline-primary">{{ __('Add column') }}</button></div>
            </div>
        </form>
    @endif

    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title mb-0">{{ __('Coefficients') }}</h3>
            @if ($canEdit)
                <a href="{{ route('admin.coefficients.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus"></i> {{ __('Add coefficient') }}</a>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                <tr><th>{{ __('Name') }}</th><th>{{ __('Coefficient') }}</th><th>{{ __('Applies to') }}</th><th>{{ __('When') }}</th><th>{{ __('Status') }}</th><th></th></tr>
                </thead>
                <tbody>
                @php $weekdayNames = [1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat'), 7 => __('Sun')]; @endphp
                @forelse ($coefficients as $c)
                    <tr @class(['text-body-secondary' => ! $c->is_active])>
                        <td><strong>{{ $c->name }}</strong><div class="small text-body-secondary">{{ $c->description }}</div></td>
                        <td>
                            × {{ rtrim(rtrim($c->coefficient, '0'), '.') }}
                            @php $pct = round(((float) $c->coefficient - 1) * 100, 1); @endphp
                            @if ($pct != 0)<span @class(['badge', 'text-bg-danger' => $pct > 0, 'text-bg-success' => $pct < 0])>{{ $pct > 0 ? '+' : '' }}{{ $pct }}%</span>@endif
                        </td>
                        <td class="small">
                            {{ collect([$c->ageGroup?->name, $c->timeSlot?->name, $c->ticketType?->name, $c->category?->name])->filter()->join(', ') ?: __('everything') }}
                        </td>
                        <td class="small">
                            {{ $c->weekdays ? collect($c->weekdays)->map(fn ($d) => $weekdayNames[$d] ?? $d)->join(', ') : __('every day') }}
                            @if ($c->date_from || $c->date_to)
                                <br>{{ $c->date_from?->format('d.m.Y') ?? '…' }} – {{ $c->date_to?->format('d.m.Y') ?? '…' }}
                            @endif
                        </td>
                        <td>{!! $c->is_active ? '<span class="badge text-bg-success">'.e(__('active')).'</span>' : '<span class="badge text-bg-secondary">'.e(__('off')).'</span>' !!}</td>
                        <td class="text-end text-nowrap">
                            @if ($canEdit)
                                <a href="{{ route('admin.coefficients.edit', $c) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.coefficients.destroy', $c) }}" class="d-inline" data-confirm="{{ __('Delete this coefficient?') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-body-secondary">{{ __('No coefficients.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
