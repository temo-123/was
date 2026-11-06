{{--
    Month calendar, weeks from Monday. Needs:
      $month    Carbon, any day of the shown month
      $monthUrl fn (Carbon $month): string    link to another month
      $dayView  view drawn inside every day, gets $date + $items[$date->toDateString()] as $items + $dayData
      $items    collection / array keyed by "Y-m-d"
    Optional: $dayUrl fn (Carbon $date): ?string (the day becomes a link), $selected "Y-m-d", $dayData array.
--}}
@php
    $month = $month->copy()->startOfMonth();
    $start = $month->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $end = $month->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SUNDAY);
    $dayData ??= [];
    $selected ??= null;
@endphp
<div class="vcal">
    <div class="vcal-head">
        <a href="{{ $monthUrl($month->copy()->subMonth()) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Previous month') }}"><i class="bi bi-chevron-left"></i></a>
        {{-- "1 October 2026" without the day: Georgian needs the month name in this form ("ოქტომბერი", not "ოქტომბერს") --}}
        <h4 class="vcal-title">{{ \Illuminate\Support\Str::ucfirst(preg_replace('/^\d+\s+/', '', $month->translatedFormat('j F Y'))) }}</h4>
        <a href="{{ $monthUrl($month->copy()->addMonth()) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Next month') }}"><i class="bi bi-chevron-right"></i></a>
        @unless ($month->isSameMonth(today()))
            <a href="{{ $monthUrl(today()) }}" class="btn btn-sm btn-link">{{ __('Today') }}</a>
        @endunless
    </div>

    <div class="vcal-grid">
        @foreach (\Carbon\CarbonPeriod::create($start, $start->copy()->addDays(6)) as $weekday)
            <div class="vcal-weekday">{{ \Illuminate\Support\Str::ucfirst($weekday->translatedFormat('D')) }}</div>
        @endforeach

        @foreach (\Carbon\CarbonPeriod::create($start, $end) as $date)
            @php
                $key = $date->toDateString();
                $dayItems = $items[$key] ?? null;
                $href = isset($dayUrl) && $date->isSameMonth($month) ? $dayUrl($date) : null;
                $hasItems = is_countable($dayItems) ? count($dayItems) > 0 : (bool) $dayItems;
            @endphp
            <{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif
                @class([
                    'vcal-day',
                    'is-other' => ! $date->isSameMonth($month),
                    'is-today' => $date->isToday(),
                    'is-weekend' => $date->isWeekend(),
                    'is-selected' => $selected === $key,
                    'has-items' => $hasItems,
                    'is-link' => (bool) $href,
                ])>
                <span class="vcal-num">{{ $date->day }}</span>
                @if ($date->isSameMonth($month))
                    @include($dayView, ['date' => $date, 'items' => $dayItems] + $dayData)
                @endif
            </{{ $href ? 'a' : 'div' }}>
        @endforeach
    </div>
</div>
