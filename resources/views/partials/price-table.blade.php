{{-- Expects: $ageGroups, $timeSlots, $ticketTypes, $categories (keyed by id), $prices (PriceRule collection) --}}
@php
    $columns = $ageGroups->flatMap(function ($group) use ($prices, $categories) {
        $cats = $prices->where('age_group_id', $group->id)->whereNotNull('visitor_category_id')->pluck('visitor_category_id')->unique();
        return $cats->isEmpty()
            ? [[$group, null]]
            : $cats->map(fn ($id) => [$group, $categories[$id] ?? null])->filter(fn ($c) => $c[1])->values();
    });
    $find = fn ($slot, $ticket, $group, $cat) => $prices->first(fn ($p) => $p->time_slot_id == $slot->id
        && $p->ticket_type_id == $ticket->id && $p->age_group_id == $group->id && $p->visitor_category_id == $cat?->id);
@endphp
<div class="table-responsive">
    <table class="table table-bordered bg-body price-table mb-0">
        <thead class="table-light">
        <tr>
            <th rowspan="2" colspan="2"></th>
            @foreach ($ageGroups as $group)
                @php $span = $columns->filter(fn ($c) => $c[0]->id == $group->id)->count(); @endphp
                <th colspan="{{ $span }}" @if ($span === 1) rowspan="2" @endif>{{ $group->label() }}</th>
            @endforeach
        </tr>
        <tr>
            @foreach ($columns as [$group, $cat])
                @if ($cat && $columns->filter(fn ($c) => $c[0]->id == $group->id)->count() > 1)
                    <th class="small fw-normal">{{ $cat->name_ka ?: $cat->name }}</th>
                @endif
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($timeSlots as $slot)
            @foreach ($ticketTypes as $ticket)
                <tr>
                    @if ($loop->first)
                        <th rowspan="{{ $ticketTypes->count() }}" class="text-nowrap">{{ $slot->label() }}</th>
                    @endif
                    <th class="small fw-normal text-start">{{ $ticket->name_ka ?: $ticket->name }}</th>
                    @foreach ($columns as [$group, $cat])
                        @php $rule = $find($slot, $ticket, $group, $cat); @endphp
                        <td>{!! $rule ? '<strong>'.rtrim(rtrim(number_format($rule->price, 2), '0'), '.').' ₾</strong>' : '<span class="text-body-secondary">—</span>' !!}</td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
</div>
