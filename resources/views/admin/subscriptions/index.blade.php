@extends('layouts.admin')

@section('title', __('Subscriptions'))

@section('content')
    <div class="card">
        <div class="card-header">
            <form class="row g-2" method="GET">
                <div class="col-md-auto">
                    <div class="btn-group">
                        @foreach (['active' => __('Active'), 'expired' => __('Expired'), 'cancelled' => __('Cancelled'), 'all' => __('All')] as $key => $label)
                            <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}" @class(['btn btn-outline-secondary', 'active' => $status === $key])>{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-4 ms-md-auto">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Visitor name or ID') }}">
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr><th>{{ __('Visitor') }}</th><th>{{ __('Type') }}</th><th>{{ __('Hours') }}</th><th>{{ __('Valid') }}</th><th>{{ __('Used') }}</th><th class="text-end">{{ __('Price') }}</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($subscriptions as $sub)
                    <tr>
                        <td><a href="{{ route('admin.visitors.show', $sub->visitor_id) }}">{{ $sub->visitor?->full_name }}</a></td>
                        <td>{{ $sub->ticketType->local_name }} <small class="text-body-secondary">{{ $sub->ageGroup?->label() }}</small></td>
                        <td>{{ $sub->timeSlot->label() }}</td>
                        <td class="text-nowrap">
                            {{ $sub->starts_on->format('d.m.Y') }} – {{ $sub->ends_on->format('d.m.Y') }}
                            @if ($sub->isActive() && $sub->ends_on->diffInDays(today(), true) <= 3)
                                <span class="badge text-bg-warning">{{ __('ends soon') }}</span>
                            @endif
                        </td>
                        <td>{{ $sub->visits_used }}{{ $sub->visits_limit ? ' / '.$sub->visits_limit : '' }}</td>
                        <td class="text-end">{{ number_format($sub->price, 2) }} ₾ <small class="text-body-secondary">{{ __($sub->payment_method) }}</small></td>
                        <td class="text-end">
                            @if ($sub->cancelled_at)
                                <span class="badge text-bg-secondary">{{ __('cancelled') }}</span>
                            @elseif ($sub->isActive())
                                @can('subscriptions.cancel')
                                    <form method="POST" action="{{ route('admin.subscriptions.cancel', $sub) }}" data-confirm="{{ __('Cancel this subscription?') }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">{{ __('Cancel') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-body-secondary">{{ __('No subscriptions.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($subscriptions->hasPages())
            <div class="card-footer">{{ $subscriptions->links() }}</div>
        @endif
    </div>
@endsection
