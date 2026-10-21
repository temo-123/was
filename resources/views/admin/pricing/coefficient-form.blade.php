@extends('layouts.admin')

@section('title', $coefficient->exists ? __('Edit coefficient') : __('New coefficient'))

@section('content')
    <form method="POST" action="{{ $coefficient->exists ? route('admin.coefficients.update', $coefficient) : route('admin.coefficients.store') }}" class="card" style="max-width: 900px">
        @csrf
        @if ($coefficient->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="name">{{ __('Name') }} *</label>
                    <input id="name" name="name" value="{{ old('name', $coefficient->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('e.g. Weekend +20%, Summer discount') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="coefficient">{{ __('Coefficient') }} *</label>
                    <input id="coefficient" type="number" step="0.001" min="0" name="coefficient" value="{{ old('coefficient', (float) $coefficient->coefficient) }}" class="form-control @error('coefficient') is-invalid @enderror" required>
                    <div class="form-text">{{ __('1 = no change, 1.2 = +20%, 0.9 = −10%') }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">{{ __('Description') }}</label>
                    <input id="description" name="description" value="{{ old('description', $coefficient->description) }}" class="form-control">
                </div>

                <div class="col-12"><hr class="my-1"><strong>{{ __('Applies to') }}</strong> <small class="text-body-secondary">{{ __('(empty = all)') }}</small></div>
                @foreach ([
                    ['age_group_id', __('Age group'), $ageGroups],
                    ['time_slot_id', __('Hours'), $timeSlots],
                    ['ticket_type_id', __('Ticket'), $ticketTypes],
                    ['visitor_category_id', __('Category'), $categories],
                ] as [$field, $label, $options])
                    <div class="col-md-3">
                        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                        <select id="{{ $field }}" name="{{ $field }}" class="form-select">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($options as $option)
                                <option value="{{ $option->id }}" @selected(old($field, $coefficient->{$field}) == $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                <div class="col-12">
                    <label class="form-label d-block">{{ __('Weekdays') }} <small class="text-body-secondary">{{ __('(none = every day)') }}</small></label>
                    @php $selected = collect(old('weekdays', $coefficient->weekdays ?? []))->map(fn ($d) => (int) $d)->all(); @endphp
                    @foreach ([1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat'), 7 => __('Sun')] as $num => $day)
                        <input type="checkbox" class="btn-check" name="weekdays[]" id="wd{{ $num }}" value="{{ $num }}" @checked(in_array($num, $selected, true))>
                        <label class="btn btn-outline-secondary btn-sm" for="wd{{ $num }}">{{ $day }}</label>
                    @endforeach
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="date_from">{{ __('From date') }}</label>
                    <input id="date_from" type="date" name="date_from" value="{{ old('date_from', $coefficient->date_from?->toDateString()) }}" class="form-control @error('date_from') is-invalid @enderror">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="date_to">{{ __('To date') }}</label>
                    <input id="date_to" type="date" name="date_to" value="{{ old('date_to', $coefficient->date_to?->toDateString()) }}" class="form-control @error('date_to') is-invalid @enderror">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $coefficient->is_active))>
                        <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('admin.pricing.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
