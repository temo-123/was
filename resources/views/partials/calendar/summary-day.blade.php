{{-- One day of the visits calendar: number of visits and money; the colour gets darker on busier days. --}}
@if ($items)
    @php($heat = $max > 0 ? round(0.08 + 0.72 * $items->visits / $max, 2) : 0)
    <div @class(['vcal-sum', 'hot' => $heat > 0.5]) style="--heat: {{ $heat }}">
        <div class="vcal-sum-count">{{ $items->visits }}</div>
        <div class="vcal-sum-money">{{ number_format($items->revenue, 0) }} ₾</div>
    </div>
@endif
