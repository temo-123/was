{{-- A visit in the visitor page tables (list and the chosen calendar day). --}}
<tr>
    <td class="text-nowrap">{{ $visit->entered_at->format('d.m.Y H:i') }}</td>
    <td class="text-center small text-body-secondary">{{ $visit->visit_number }}</td>
    <td>{{ $visit->age_at_visit }}</td>
    <td>{{ $visit->ticketType?->local_name }}</td>
    <td>{{ $visit->timeSlot?->label() }}</td>
    <td class="text-end">@include('admin.partials.visit-price')</td>
    <td><small>{{ $visit->creator?->name }}</small></td>
    <td class="text-end">
        @can('visits.delete')
            <form method="POST" action="{{ route('admin.visits.destroy', $visit) }}" data-confirm="{{ __('Cancel this visit?') }}">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-link text-danger p-0" title="{{ __('Cancel visit') }}"><i class="bi bi-x-circle"></i></button>
            </form>
        @endcan
    </td>
</tr>
@if ($visit->comment)
    <tr><td colspan="8" class="small text-body-secondary pt-0"><i class="bi bi-chat-left-text"></i> {{ $visit->comment }}</td></tr>
@endif
