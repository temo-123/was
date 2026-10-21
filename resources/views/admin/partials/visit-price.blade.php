@if ($visit->payment_method === 'subscription')
    <span class="badge text-bg-info">{{ __('subscription') }}</span>
@elseif ($visit->payment_method === 'free')
    <span class="badge text-bg-secondary">{{ __('free') }}</span>
@else
    <strong>{{ number_format($visit->price, 2) }} ₾</strong>
    <small class="text-body-secondary">{{ __($visit->payment_method) }}</small>
    @if ($visit->price_overridden)
        <i class="bi bi-pencil-square text-warning" title="{{ __('Price changed manually') }}"></i>
    @endif
@endif
