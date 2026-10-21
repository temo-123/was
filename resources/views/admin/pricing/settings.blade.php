@extends('layouts.admin')

@section('title', __('Age groups & hours'))

@section('actions')
    <a href="{{ route('admin.pricing.index') }}" class="btn btn-outline-secondary"><i class="bi bi-cash-coin"></i> {{ __('Price list') }}</a>
@endsection

@section('content')
    @php
        // [type, title, help, records, extra fields: name => [label, input type]]
        $sections = [
            ['age-groups', __('Age groups'), __('From age is inclusive, "to age" is exclusive: 4–10 means 4 to 9 years. Empty "to" = no upper limit.'), $ageGroups, [
                'min_age' => [__('From age'), 'number'],
                'max_age' => [__('To age'), 'number'],
            ]],
            ['time-slots', __('Hours (time slots)'), __('Start inclusive, end exclusive. The price is chosen by the time of entry. A subscription also works in earlier slots (lower order).'), $timeSlots, [
                'start_time' => [__('Start'), 'time'],
                'end_time' => [__('End'), 'time'],
            ]],
            ['ticket-types', __('Ticket types'), __('Empty "days" = single visit. With days = subscription valid that many days. Empty "limit" = unlimited entries.'), $ticketTypes, [
                'duration_days' => [__('Days'), 'number'],
                'visits_limit' => [__('Entries limit'), 'number'],
            ]],
            ['categories', __('Visitor categories'), __('E.g. regular / member. The default category is given to new visitors.'), $categories, [
                'is_default' => [__('Default'), 'checkbox'],
            ]],
        ];
    @endphp

    @foreach ($sections as [$type, $title, $help, $records, $fields])
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">{{ $title }}</h3>
            </div>
            <div class="card-body pb-0"><p class="small text-body-secondary">{{ $help }}</p></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Name (KA)') }}</th>
                        @foreach ($fields as [$label]) <th>{{ $label }}</th> @endforeach
                        <th>{{ __('Order') }}</th><th>{{ __('Active') }}</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($records->concat([null]) as $record)
                        @php $formId = $type.'-'.($record->id ?? 'new'); @endphp
                        <tr @class(['table-light' => ! $record])>
                            <td><input form="{{ $formId }}" name="code" value="{{ $record?->code }}" class="form-control form-control-sm" placeholder="{{ $record ? '' : __('new…') }}" required></td>
                            <td><input form="{{ $formId }}" name="name" value="{{ $record?->name }}" class="form-control form-control-sm" required></td>
                            <td><input form="{{ $formId }}" name="name_ka" value="{{ $record?->name_ka }}" class="form-control form-control-sm"></td>
                            @foreach ($fields as $field => [$label, $inputType])
                                <td>
                                    @if ($inputType === 'checkbox')
                                        <input form="{{ $formId }}" type="hidden" name="{{ $field }}" value="0">
                                        <input form="{{ $formId }}" type="checkbox" name="{{ $field }}" value="1" class="form-check-input" @checked($record?->{$field})>
                                    @else
                                        @php $value = $record?->{$field}; if ($inputType === 'time' && $value) { $value = substr($value, 0, 5); } @endphp
                                        <input form="{{ $formId }}" type="{{ $inputType }}" name="{{ $field }}" value="{{ $value }}" class="form-control form-control-sm" style="max-width: 120px">
                                    @endif
                                </td>
                            @endforeach
                            <td><input form="{{ $formId }}" type="number" name="sort_order" value="{{ $record?->sort_order ?? 0 }}" class="form-control form-control-sm" style="max-width: 70px"></td>
                            <td>
                                <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($record ? $record->is_active : true)>
                            </td>
                            <td class="text-end">
                                <form id="{{ $formId }}" method="POST" action="{{ route('admin.pricing.settings.save', $type) }}">
                                    @csrf
                                    @if ($record)<input type="hidden" name="id" value="{{ $record->id }}">@endif
                                    <button class="btn btn-sm {{ $record ? 'btn-outline-primary' : 'btn-primary' }}">
                                        <i class="bi {{ $record ? 'bi-save' : 'bi-plus' }}"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection
