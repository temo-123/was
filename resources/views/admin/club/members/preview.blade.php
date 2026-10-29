@extends('layouts.admin')

@section('title', __('Import preview'))

@section('content')
    @php
        $badges = [
            \App\Services\Club\MemberImporter::NEW => ['success', __('New')],
            \App\Services\Club\MemberImporter::DUPLICATE => ['secondary', __('Already added')],
            \App\Services\Club\MemberImporter::INVALID => ['danger', __('Error')],
        ];
    @endphp

    <div class="row g-3 mb-3">
        @foreach ($badges as $status => [$color, $label])
            <div class="col-md-4">
                <div class="small-box text-bg-{{ $color }} mb-0 p-3 rounded">
                    <div class="fs-2 fw-bold">{{ $summary[$status] }}</div>
                    <div>{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.club.import.store') }}" class="card">
        @csrf
        <input type="hidden" name="key" value="{{ $key }}">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <span class="me-auto"><i class="bi bi-file-earmark-excel"></i> {{ $fileName }}</span>
            <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="show" id="show-all" checked data-filter="">
                <label class="btn btn-outline-secondary" for="show-all">{{ __('All') }}</label>
                @foreach ($badges as $status => [$color, $label])
                    <input type="radio" class="btn-check" name="show" id="show-{{ $status }}" data-filter="{{ $status }}">
                    <label class="btn btn-outline-{{ $color }}" for="show-{{ $status }}">{{ $label }}</label>
                @endforeach
            </div>
        </div>
        <div class="table-responsive" style="max-height: 60vh">
            <table class="table table-sm table-hover mb-0" id="import-rows">
                <thead class="sticky-top">
                <tr>
                    <th>{{ __('Row') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('ID number') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Date of birth') }}</th>
                    <th>{{ __('Details') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($items as $item)
                    @php([$color, $label] = $badges[$item['status']])
                    <tr data-status="{{ $item['status'] }}">
                        <td class="text-body-secondary">{{ $item['line'] }}</td>
                        <td><span class="badge text-bg-{{ $color }}">{{ $label }}</span></td>
                        <td>{{ trim(($item['data']['first_name'] ?? '').' '.($item['data']['last_name'] ?? '')) ?: '—' }}</td>
                        <td class="font-monospace">{{ $item['data']['personal_id'] ?? '' }}</td>
                        <td class="font-monospace">{{ $item['data']['phone'] ?? '' }}</td>
                        <td>{{ $item['data']['email'] ?? '' }}</td>
                        <td>{{ $item['data']['birth_date'] ?? '' }}</td>
                        <td class="small">
                            @if ($item['match'])
                                {{ __('Same :field as', ['field' => mb_strtolower($item['match']['field'])]) }}
                                @if ($item['match']['id'])
                                    <a href="{{ route('admin.club.members.edit', $item['match']['id']) }}" target="_blank">{{ $item['match']['name'] }}</a>
                                @else
                                    {{ $item['match']['name'] }}
                                @endif
                            @endif
                            @foreach ($item['errors'] as $error)
                                <div class="text-danger">{{ $error }}</div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex gap-2 align-items-center">
            @if ($summary['new'])
                <button class="btn btn-success"><i class="bi bi-check2"></i> {{ trans_choice('Import :count new member|Import :count new members', $summary['new']) }}</button>
            @else
                <span class="text-body-secondary">{{ __('There are no new members in this file.') }}</span>
            @endif
            <a href="{{ route('admin.club.import.create') }}" class="btn btn-outline-secondary">{{ __('Choose another file') }}</a>
            <small class="text-body-secondary ms-auto">{{ __('Rows marked "Already added" or "Error" are skipped.') }}</small>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-filter]').forEach((radio) => radio.addEventListener('change', () => {
            document.querySelectorAll('#import-rows tbody tr').forEach((row) => {
                row.hidden = radio.dataset.filter !== '' && row.dataset.status !== radio.dataset.filter;
            });
        }));
    </script>
@endpush
