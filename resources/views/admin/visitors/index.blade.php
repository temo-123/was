@extends('layouts.admin')

@section('title', __('Visitors'))

@section('actions')
    @can('visitors.create')
        <a href="{{ route('admin.visitors.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> {{ __('New visitor') }}</a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form class="row g-2" method="GET">
                <div class="col-md-6">
                    <input name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Name, surname, ID number, phone, email…') }}" autofocus>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="sort" class="form-select">
                        <option value="">{{ __('Sort by name') }}</option>
                        <option value="recent" @selected(request('sort') === 'recent')>{{ __('Last visit first') }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-secondary"><i class="bi bi-search"></i> {{ __('Search') }}</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('ID number') }}</th>
                    <th>{{ __('Age') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th class="text-center">{{ __('Visits') }}</th>
                    <th>{{ __('Last visit') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($visitors as $visitor)
                    <tr>
                        <td>
                            <a href="{{ route('admin.visitors.show', $visitor) }}" class="fw-semibold">{{ $visitor->full_name }}</a>
                            @if ($visitor->user)<i class="bi bi-person-check text-success" title="{{ __('Has an online account') }}"></i>@endif
                            @unless ($visitor->terms_accepted)<span class="badge text-bg-warning">{{ __('no terms') }}</span>@endunless
                        </td>
                        <td>{{ $visitor->personal_id }}</td>
                        <td>{{ $visitor->ageAt() }}</td>
                        <td>{{ $visitor->phone }}</td>
                        <td>{{ $visitor->category?->name }}</td>
                        <td class="text-center">{{ $visitor->visits_count }}</td>
                        <td>{{ $visitor->visits_max_entered_at ? \Illuminate\Support\Carbon::parse($visitor->visits_max_entered_at)->format('d.m.Y H:i') : '—' }}</td>
                        <td class="text-end text-nowrap">
                            @can('visits.create')
                                <a href="{{ route('admin.entry', ['visitor' => $visitor->id]) }}" class="btn btn-sm btn-success" title="{{ __('Register entrance') }}"><i class="bi bi-door-open"></i></a>
                            @endcan
                            @can('visitors.update')
                                <a href="{{ route('admin.visitors.edit', $visitor) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-body-secondary">{{ __('No visitors found.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($visitors->hasPages())
            <div class="card-footer">{{ $visitors->links() }}</div>
        @endif
    </div>
@endsection
