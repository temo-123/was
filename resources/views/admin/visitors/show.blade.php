@extends('layouts.admin')

@section('title', $visitor->full_name)

@section('actions')
    @can('visits.create')
        <a href="{{ route('admin.entry', ['visitor' => $visitor->id]) }}" class="btn btn-success"><i class="bi bi-door-open"></i> {{ __('Register entrance') }}</a>
    @endcan
    @can('visitors.update')
        <a href="{{ route('admin.visitors.edit', $visitor) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
    @endcan
    @can('visitors.delete')
        <form method="POST" action="{{ route('admin.visitors.destroy', $visitor) }}" class="d-inline" data-confirm="{{ __('Delete this visitor?') }}">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    @endcan
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Visitor card') }}</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('ID number') }}</dt><dd class="col-7">{{ $visitor->personal_id }}</dd>
                        <dt class="col-5">{{ __('Date of birth') }}</dt><dd class="col-7">{{ $visitor->birth_date->format('d.m.Y') }} ({{ $visitor->ageAt() }})</dd>
                        <dt class="col-5">{{ __('Phone') }}</dt><dd class="col-7"><a href="tel:{{ $visitor->phone }}">{{ $visitor->phone }}</a></dd>
                        <dt class="col-5">{{ __('Email') }}</dt><dd class="col-7">{{ $visitor->email ?: '—' }}</dd>
                        <dt class="col-5">{{ __('Category') }}</dt><dd class="col-7">{{ $visitor->category?->local_name ?? '—' }}</dd>
                        <dt class="col-5">{{ __('Gym terms') }}</dt>
                        <dd class="col-7">
                            @if ($visitor->terms_accepted)
                                <span class="text-success"><i class="bi bi-check-circle"></i> {{ $visitor->terms_accepted_at?->format('d.m.Y') }}</span>
                            @else
                                <span class="badge text-bg-warning">{{ __('not accepted') }}</span>
                            @endif
                        </dd>
                        <dt class="col-5">{{ __('Registered') }}</dt><dd class="col-7">{{ $visitor->created_at->format('d.m.Y') }} <small class="text-body-secondary">{{ $visitor->creator?->name }}</small></dd>
                    </dl>
                    @if ($visitor->comment)
                        <div class="alert alert-warning mt-3 mb-0 small"><i class="bi bi-chat-left-text me-1"></i>{{ $visitor->comment }}</div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col"><div class="h4 mb-0">{{ $stats['visits'] }}</div><small class="text-body-secondary">{{ __('visits') }}</small></div>
                        <div class="col"><div class="h4 mb-0">{{ number_format($stats['spent'], 0) }} ₾</div><small class="text-body-secondary">{{ __('spent') }}</small></div>
                        <div class="col"><div class="h6 mb-0 mt-1">{{ $stats['last_visit'] ? \Illuminate\Support\Carbon::parse($stats['last_visit'])->format('d.m.Y') : '—' }}</div><small class="text-body-secondary">{{ __('last visit') }}</small></div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-person-badge me-1"></i> {{ __('Online account') }}</h3></div>
                <div class="card-body">
                    @if ($visitor->user)
                        <p class="mb-1"><strong>{{ $visitor->user->name }}</strong></p>
                        <p class="mb-1 small">{{ $visitor->user->email }}</p>
                        <p class="small text-body-secondary">
                            {{ __('Linked via :via', ['via' => $visitor->userLink->linked_via]) }}
                            · {{ $visitor->userLink->created_at->format('d.m.Y') }}
                            @if ($visitor->userLink->linker) · {{ $visitor->userLink->linker->name }} @endif
                        </p>
                        @can('visitors.link')
                            <form method="POST" action="{{ route('admin.visitors.unlink', $visitor) }}" data-confirm="{{ __('Remove the link?') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Unlink') }}</button>
                            </form>
                        @endcan
                    @else
                        <p class="small text-body-secondary">{{ __('Not linked. It links automatically when the visitor registers with the same ID number or a verified email.') }}</p>
                        @can('visitors.link')
                            <form method="POST" action="{{ route('admin.visitors.link', $visitor) }}" class="input-group input-group-sm">
                                @csrf
                                <input type="email" name="email" class="form-control" placeholder="{{ __('User email') }}" required>
                                <button class="btn btn-outline-primary">{{ __('Link') }}</button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Subscriptions') }}</h3></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Hours') }}</th><th>{{ __('Valid') }}</th><th>{{ __('Used') }}</th><th>{{ __('Price') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($subscriptions as $sub)
                            <tr @class(['table-success' => $sub->isActive(), 'text-body-secondary' => ! $sub->isActive()])>
                                <td>{{ $sub->ticketType->local_name }}</td>
                                <td>{{ $sub->timeSlot->label() }}</td>
                                <td>{{ $sub->starts_on->format('d.m.Y') }} – {{ $sub->ends_on->format('d.m.Y') }}</td>
                                <td>{{ $sub->visits_used }}{{ $sub->visits_limit ? ' / '.$sub->visits_limit : '' }}</td>
                                <td>{{ number_format($sub->price, 2) }} ₾</td>
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
                            <tr><td colspan="6" class="text-body-secondary">{{ __('No subscriptions.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ __('Visits') }}</h3></div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Age') }}</th><th>{{ __('Ticket') }}</th><th>{{ __('Hours') }}</th><th class="text-end">{{ __('Price') }}</th><th>{{ __('Staff') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($visits as $visit)
                            <tr>
                                <td>{{ $visit->entered_at->format('d.m.Y H:i') }}</td>
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
                                <tr><td colspan="7" class="small text-body-secondary pt-0"><i class="bi bi-chat-left-text"></i> {{ $visit->comment }}</td></tr>
                            @endif
                        @empty
                            <tr><td colspan="7" class="text-body-secondary">{{ __('No visits yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($visits->hasPages())
                    <div class="card-footer">{{ $visits->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
