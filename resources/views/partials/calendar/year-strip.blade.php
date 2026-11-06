{{-- The 12 months of $month's year with their visit count ($counts: month number => count); click = that month. --}}
<div class="vcal-months">
    @foreach (range(1, 12) as $m)
        @php($date = $month->copy()->setDate($month->year, $m, 1))
        <a href="{{ $monthUrl($date) }}" @class(['active' => $m === $month->month, 'empty' => empty($counts[$m])])>
            <span>{{ \Illuminate\Support\Str::ucfirst($date->translatedFormat('M')) }}</span>
            <b>{{ $counts[$m] ?? 0 }}</b>
        </a>
    @endforeach
</div>
