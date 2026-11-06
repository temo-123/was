{{-- One day of a visitor's calendar: a chip per visit (time, subscription / paid / free, gear, reward, status). --}}
@foreach (collect($items)->take(3) as $visit)
    @php
        $kind = $visit->visitor_subscription_id ? 'subscription' : ($visit->total_price > 0 ? 'paid' : 'free');
        $title = collect([
            $visit->entered_at->format('H:i').' · '.($visit->ticketType?->local_name ?? ''),
            __('visit no. :number', ['number' => $visit->visit_number]),
            $visit->total_price > 0 ? number_format($visit->total_price, 2).' ₾' : ($kind === 'subscription' ? __('subscription') : __('free')),
            $visit->rentals->map(fn ($r) => $r->local_name.($r->size ? ' '.$r->size : ''))->implode(', '),
            collect($visit->rewards)->pluck('name')->implode(', '),
        ])->filter()->implode("\n");
    @endphp
    <span class="vcal-chip {{ $kind }}" title="{{ $title }}">
        <b>{{ $visit->entered_at->format('H:i') }}</b>
        @if ($kind === 'subscription')<i class="bi bi-card-checklist"></i>@endif
        @if ($visit->rentals->isNotEmpty())<i class="bi bi-bag"></i>@endif
        @if ($visit->rewards)<i class="bi bi-gift"></i>@endif
        @if ($visit->discount_percent > 0)<i class="bi bi-star-fill"></i>@endif
        @if ($visit->total_price > 0)<span class="ms-auto">{{ rtrim(rtrim(number_format($visit->total_price, 2), '0'), '.') }}₾</span>@endif
    </span>
@endforeach
@if (collect($items)->count() > 3)
    <span class="vcal-more">+{{ collect($items)->count() - 3 }}</span>
@endif
