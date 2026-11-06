@extends('layouts.admin')

@section('title', __('Rental gear'))

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="info-box mb-0"><span class="info-box-icon text-bg-warning"><i class="bi bi-arrow-return-left"></i></span>
                <div class="info-box-content"><span class="info-box-text">{{ __('Out now (not returned)') }}</span><span class="info-box-number">{{ $out->sum('quantity') }}</span></div></div>
        </div>
        <div class="col-md-4">
            <div class="info-box mb-0"><span class="info-box-icon text-bg-primary"><i class="bi bi-bag"></i></span>
                <div class="info-box-content"><span class="info-box-text">{{ __('Rented today') }}</span><span class="info-box-number">{{ $rentedToday->pieces }}</span></div></div>
        </div>
        <div class="col-md-4">
            <div class="info-box mb-0"><span class="info-box-icon text-bg-success"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content"><span class="info-box-text">{{ __('Gear money today') }}</span><span class="info-box-number">{{ number_format($rentedToday->money, 2) }} ₾</span></div></div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Gear that should come back --}}
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-arrow-return-left me-1"></i> {{ __('Rented now') }}</h3></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead><tr><th>{{ __('Visitor') }}</th><th>{{ __('Gear') }}</th><th>{{ __('Since') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($out as $rental)
                            <tr @class(['table-warning' => ! $rental->visit->entered_at->isToday()])>
                                <td>
                                    <a href="{{ route('admin.visitors.show', $rental->visit->visitor_id) }}" class="fw-semibold">{{ $rental->visit->visitor?->full_name }}</a>
                                    <small class="text-body-secondary d-block">{{ $rental->visit->visitor?->phone }}</small>
                                </td>
                                <td>
                                    <i class="bi {{ $rental->is_reward ? 'bi-gift text-success' : 'bi-bag' }}"></i>
                                    {{ $rental->local_name }}
                                    @if ($rental->size)<span class="badge text-bg-light border">{{ __('size :size', ['size' => $rental->size]) }}</span>@endif
                                    @if ($rental->quantity > 1)<span class="badge text-bg-secondary">×{{ $rental->quantity }}</span>@endif
                                </td>
                                <td class="text-nowrap">
                                    {{ $rental->created_at->isToday() ? $rental->created_at->format('H:i') : $rental->created_at->format('d.m H:i') }}
                                    @unless ($rental->visit->entered_at->isToday())
                                        <span class="badge text-bg-danger">{{ __('not returned since :date', ['date' => $rental->visit->entered_at->format('d.m')]) }}</span>
                                    @endunless
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.rentals.returned', $rental) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success"><i class="bi bi-check2"></i> {{ __('Returned') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-4"><i class="bi bi-check2-all fs-4 d-block"></i>{{ __('All gear is back.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Someone already in the gym wants gear --}}
        <div class="col-xl-5">
            @can('visits.create')
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-plus-circle me-1"></i> {{ __('Give gear to a visitor of today') }}</h3></div>
                    <div class="card-body">
                        @if ($todayVisits->isEmpty() || $items->where('is_active', true)->isEmpty())
                            <p class="text-body-secondary mb-0">
                                {{ $todayVisits->isEmpty() ? __('Nobody has entered today yet.') : __('Add rental gear below first.') }}
                                {{ __('Gear is normally chosen at the entrance.') }}
                            </p>
                        @else
                            <form method="POST" action="{{ route('admin.rentals.add') }}" class="row g-2">
                                @csrf
                                <div class="col-12">
                                    <label class="form-label small">{{ __('Visitor (entered today)') }}</label>
                                    <select name="visitor_visit_id" class="form-select" required>
                                        @foreach ($todayVisits as $visit)
                                            <option value="{{ $visit->id }}">{{ $visit->entered_at->format('H:i') }} — {{ $visit->visitor?->full_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-7">
                                    <label class="form-label small">{{ __('Gear') }}</label>
                                    <select name="rental_item_id" class="form-select" required>
                                        @foreach ($items->where('is_active', true) as $item)
                                            <option value="{{ $item->id }}">{{ $item->local_name }} — {{ number_format($item->price, 2) }} ₾</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-2">
                                    <label class="form-label small">{{ __('Pcs') }}</label>
                                    <input type="number" name="quantity" value="1" min="1" max="20" class="form-control">
                                </div>
                                <div class="col-3">
                                    <label class="form-label small">{{ __('Size') }}</label>
                                    <input name="size" maxlength="16" class="form-control" placeholder="42">
                                </div>
                                <div class="col-12">
                                    <div class="btn-group w-100" role="group">
                                        @foreach ($paymentMethods as $method)
                                            <input type="radio" class="btn-check" name="payment_method" id="pay-{{ $method }}" value="{{ $method }}" @checked($loop->first)>
                                            <label class="btn btn-outline-secondary" for="pay-{{ $method }}">{{ __($method === 'transfer' ? 'Bank transfer' : ucfirst($method)) }}</label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary w-100"><i class="bi bi-bag-plus"></i> {{ __('Add to the visit') }}</button>
                                    <div class="form-text">{{ __('The price is added to that visit (with the client status gear discount).') }}</div>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan
        </div>
    </div>

    {{-- The gear and its prices --}}
    <div class="card mt-3">
        <div class="card-header"><h3 class="card-title"><i class="bi bi-bag me-1"></i> {{ __('Gear and prices') }}</h3></div>
        <div class="card-body pb-0">
            <p class="small text-body-secondary">
                {{ __('"Sizes": staff writes the size (climbing shoes). "Comes back": shown in "Rented now" until it is returned (chalk does not come back). "Pieces": how many the gym has, to warn when all are out — empty = not counted.') }}
            </p>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('Name') }}</th><th>{{ __('Name (KA)') }}</th><th>{{ __('Price') }}</th><th class="text-center">{{ __('Sizes') }}</th>
                    <th class="text-center">{{ __('Comes back') }}</th><th>{{ __('Pieces') }}</th><th class="text-center">{{ __('Out') }}</th>
                    <th>{{ __('Order') }}</th><th class="text-center">{{ __('Active') }}</th><th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($items->concat([null]) as $item)
                    @php($formId = 'item-'.($item->id ?? 'new'))
                    <tr @class(['table-light' => ! $item, 'opacity-50' => $item && ! $item->is_active])>
                        @can('rentals.manage')
                            <td><input form="{{ $formId }}" name="name" value="{{ $item?->name }}" class="form-control form-control-sm" placeholder="{{ $item ? '' : __('e.g. Climbing shoes') }}" required></td>
                            <td><input form="{{ $formId }}" name="name_ka" value="{{ $item?->name_ka }}" class="form-control form-control-sm" placeholder="{{ $item ? '' : 'საცოცი ფეხსაცმელი' }}"></td>
                            <td>
                                <div class="input-group input-group-sm" style="max-width: 120px">
                                    <input form="{{ $formId }}" type="number" step="0.01" min="0" name="price" value="{{ $item?->price }}" class="form-control" required>
                                    <span class="input-group-text">₾</span>
                                </div>
                            </td>
                            <td class="text-center"><input form="{{ $formId }}" type="checkbox" name="has_sizes" value="1" class="form-check-input" @checked($item?->has_sizes)></td>
                            <td class="text-center"><input form="{{ $formId }}" type="checkbox" name="is_returnable" value="1" class="form-check-input" @checked($item ? $item->is_returnable : true)></td>
                            <td><input form="{{ $formId }}" type="number" min="0" name="stock" value="{{ $item?->stock }}" class="form-control form-control-sm" style="max-width: 80px"></td>
                            <td class="text-center">{{ $item ? ($outByItem[$item->id] ?? 0) : '' }}</td>
                            <td><input form="{{ $formId }}" type="number" min="0" name="sort_order" value="{{ $item?->sort_order ?? 0 }}" class="form-control form-control-sm" style="max-width: 70px"></td>
                            <td class="text-center"><input form="{{ $formId }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($item ? $item->is_active : true)></td>
                            <td class="text-end">
                                <form id="{{ $formId }}" method="POST" action="{{ route('admin.rentals.items.save') }}">
                                    @csrf
                                    @if ($item)<input type="hidden" name="id" value="{{ $item->id }}">@endif
                                    <button class="btn btn-sm {{ $item ? 'btn-outline-primary' : 'btn-primary' }}" title="{{ $item ? __('Save') : __('Add') }}"><i class="bi {{ $item ? 'bi-save' : 'bi-plus' }}"></i></button>
                                </form>
                            </td>
                        @elseif ($item)
                            <td>{{ $item->name }}</td><td>{{ $item->name_ka }}</td><td>{{ number_format($item->price, 2) }} ₾</td>
                            <td class="text-center">{!! $item->has_sizes ? '<i class="bi bi-check"></i>' : '' !!}</td>
                            <td class="text-center">{!! $item->is_returnable ? '<i class="bi bi-check"></i>' : '' !!}</td>
                            <td>{{ $item->stock ?? '—' }}</td><td class="text-center">{{ $outByItem[$item->id] ?? 0 }}</td><td>{{ $item->sort_order }}</td>
                            <td class="text-center">{!! $item->is_active ? '<i class="bi bi-check"></i>' : '' !!}</td><td></td>
                        @endcan
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
