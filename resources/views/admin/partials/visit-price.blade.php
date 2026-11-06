{{-- Money of one visit: total, then what it is made of (subscription, rental gear, status discount, rewards). --}}
@php($bySubscription = (bool) $visit->visitor_subscription_id)
@if ($visit->total_price > 0)
    <strong>{{ number_format($visit->total_price, 2) }} ₾</strong>
    <small class="text-body-secondary">{{ __($visit->payment_method) }}</small>
    @if ($visit->price_overridden)
        <i class="bi bi-pencil-square text-warning" title="{{ __('Price changed manually') }}"></i>
    @endif
    @if ($bySubscription)
        <span class="badge text-bg-info">{{ __('subscription') }}</span>
    @endif
@elseif ($bySubscription)
    <span class="badge text-bg-info">{{ __('subscription') }}</span>
@else
    <span class="badge text-bg-secondary">{{ __('free') }}</span>
@endif

@if ($visit->rentals->isNotEmpty() || $visit->discount_amount > 0 || $visit->rewards)
    <div class="small text-body-secondary lh-sm mt-1">
        @if ($visit->rentals->isNotEmpty() && $visit->price > 0)
            <span title="{{ __('Entry') }}">{{ number_format($visit->price, 2) }} ₾</span> +
        @endif
        @foreach ($visit->rentals as $rental)
            <span class="text-nowrap" title="{{ $rental->is_returnable ? ($rental->returned_at ? __('Returned :time', ['time' => $rental->returned_at->format('d.m H:i')]) : __('Not returned yet')) : '' }}">
                <i class="bi {{ $rental->is_reward ? 'bi-gift text-success' : 'bi-bag' }}"></i>{{ $rental->local_name }}@if ($rental->size) {{ $rental->size }}@endif @if ($rental->quantity > 1)×{{ $rental->quantity }}@endif
                @if ($rental->is_returnable && ! $rental->returned_at)<i class="bi bi-arrow-return-left text-warning"></i>@endif
            </span>
        @endforeach
        @if ($visit->discount_percent > 0 && $visit->tier)
            <span class="badge rounded-pill" style="background: {{ $visit->tier->color }}; color: #111">{{ $visit->tier->local_name }} {{ \App\Models\ClientTier::discountLabel((float) $visit->discount_percent) }}</span>
        @endif
        @foreach ($visit->rewards ?? [] as $reward)
            <span class="badge rounded-pill text-bg-success"><i class="bi bi-gift"></i> {{ app()->getLocale() === 'ka' && filled($reward['name_ka'] ?? null) ? $reward['name_ka'] : $reward['name'] }}</span>
        @endforeach
    </div>
@endif
