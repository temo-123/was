@extends('layouts.admin')

@section('title', __('Tech tips'))

@section('actions')
    <a href="{{ route('tips.index') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-tablet-landscape"></i> {{ __('Open the tablet page') }}</a>
    @can('tips.manage')
        <a href="{{ route('admin.tech-tips.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New tip') }}</a>
    @endcan
@endsection

@section('content')
    {{-- How to open the page on the tablet --}}
    <div class="card mb-3 border-primary-subtle">
        <div class="card-body d-flex flex-column flex-md-row gap-4 align-items-md-center">
            <div class="flex-none bg-white rounded-3 p-2 border align-self-center tablet-qr">{!! $tabletSvg !!}</div>
            <div class="flex-grow-1">
                <div class="text-primary fw-semibold small text-uppercase"><i class="bi bi-tablet-landscape"></i> {{ __('Tablet in the gym') }}</div>
                <div class="fs-4 fw-bold font-monospace user-select-all my-1">{{ $tabletUrl }}</div>
                <ol class="mb-0 ps-3 small text-body-secondary">
                    <li>{{ __('Connect the tablet to the same Wi-Fi as this computer.') }}</li>
                    <li>{{ __('Scan the code with the tablet camera, or type the address into Chrome.') }}</li>
                    <li>{{ __('Tap the full screen button in the top corner of the page. Chrome menu → "Add to Home screen" makes an icon for it.') }}</li>
                </ol>
                @if (config('tips.port'))
                    <div class="small text-body-secondary mt-2">
                        <i class="bi bi-info-circle"></i>
                        {{ __('On port :port the computer only shows the tips (no admin panel). The port must be open in Apache and in the firewall — see deploy/apache/README.md.', ['port' => config('tips.port')]) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <form class="row g-2 align-items-end" method="GET">
                <div class="col-md-4">
                    <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Title or short description…') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label for="category" class="form-label small mb-1">{{ __('Topic') }}</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">{{ __('All topics') }}</option>
                        @foreach (config('tips.categories') as $key => $category)
                            <option value="{{ $key }}" @selected(request('category') === $key)>{{ __($category['label']) }} ({{ $counts[$key] ?? 0 }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        <option value="published" @selected(request('status') === 'published')>{{ __('Shown on the tablet') }}</option>
                        <option value="hidden" @selected(request('status') === 'hidden')>{{ __('Hidden') }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-secondary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    @if ($filtered)
                        <a href="{{ route('admin.tech-tips.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear') }}"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th style="width: 96px"></th>
                    <th>{{ __('Tip') }}</th>
                    <th>{{ __('Topic') }}</th>
                    <th>{{ __('Level') }}</th>
                    <th class="text-center">{{ __('Slides') }}</th>
                    <th class="text-center">{{ __('Order') }}</th>
                    <th class="text-center">{{ __('Read') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($tips as $tip)
                    @php($article = $tip->article())
                    <tr @class(['opacity-75' => ! $tip->is_published])>
                        <td>
                            @if ($tip->image)
                                <img src="{{ $tip->imageUrl() }}" alt="" class="rounded tip-thumb" loading="lazy">
                            @else
                                <div class="rounded tip-thumb d-flex align-items-center justify-content-center bg-body-secondary text-body-tertiary fs-3"><i class="bi {{ $tip->categoryIcon() }}"></i></div>
                            @endif
                        </td>
                        <td>
                            <a href="{{ auth()->user()->can('tips.manage') ? route('admin.tech-tips.edit', $tip) : route('tips.index') }}" class="fw-semibold">{{ $article?->title }}</a>
                            @if ($article?->short_description)
                                <div class="small text-body-secondary text-truncate" style="max-width: 420px">{{ $article->short_description }}</div>
                            @endif
                            <div class="mt-1">
                                @foreach (config('app.locales') as $locale => $language)
                                    @if ($tip->articles->contains('locale', $locale))
                                        <span class="badge text-bg-light border" title="{{ $language }}">{{ strtoupper($locale) }}</span>
                                    @else
                                        <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle" title="{{ __('Not translated: :language', ['language' => $language]) }}">
                                            <s>{{ strtoupper($locale) }}</s>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td class="text-nowrap"><i class="bi {{ $tip->categoryIcon() }} text-primary"></i> {{ $tip->categoryLabel() }}</td>
                        <td class="text-nowrap">{{ $tip->levelLabel() ?? __('For everyone') }}</td>
                        <td class="text-center">{{ $tip->slides_count }}</td>
                        <td class="text-center">{{ $tip->sort_order }}</td>
                        <td class="text-center">{{ $tip->views }}</td>
                        <td>
                            @can('tips.manage')
                                <form method="POST" action="{{ route('admin.tech-tips.toggle', $tip) }}">
                                    @csrf
                                    <button @class(['btn btn-sm', 'btn-success' => $tip->is_published, 'btn-outline-secondary' => ! $tip->is_published])
                                            title="{{ $tip->is_published ? __('Hide from the tablet') : __('Show on the tablet') }}">
                                        <i @class(['bi', 'bi-eye' => $tip->is_published, 'bi-eye-slash' => ! $tip->is_published])></i>
                                        {{ $tip->is_published ? __('shown') : __('hidden') }}
                                    </button>
                                </form>
                            @else
                                <span @class(['badge', 'text-bg-success' => $tip->is_published, 'text-bg-secondary' => ! $tip->is_published])>{{ $tip->is_published ? __('shown') : __('hidden') }}</span>
                            @endcan
                        </td>
                        <td class="text-end text-nowrap">
                            @can('tips.manage')
                                <a href="{{ route('admin.tech-tips.edit', $tip) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-body-secondary">
                            <i class="bi bi-lightbulb display-6 d-block mb-2"></i>
                            {{ $filtered ? __('No tips match the filter.') : __('No tips yet. Write the first one — it appears on the tablet right away.') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($tips->hasPages())
            <div class="card-footer">{{ $tips->links() }}</div>
        @endif
    </div>
@endsection

@push('head')
    <style>
        .tip-thumb { width: 80px; height: 56px; object-fit: cover; }
        .tablet-qr svg { width: 150px; height: 150px; display: block; }
    </style>
@endpush
