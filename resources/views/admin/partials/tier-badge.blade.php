{{-- Client status of a visitor (VIP, Gold…) as a coloured pill; nothing when there's none or it expired. --}}
@if ($tier = $visitor->activeTier())
    <span class="badge rounded-pill" style="background: {{ $tier->color }}; color: #111" title="{{ $visitor->client_tier_until ? __('until :date', ['date' => $visitor->client_tier_until->format('d.m.Y')]) : '' }}">
        <i class="bi bi-star-fill"></i> {{ $tier->local_name }}
        @if ($tier->discount_percent > 0) {{ \App\Models\ClientTier::discountLabel((float) $tier->discount_percent) }} @endif
    </span>
@endif
