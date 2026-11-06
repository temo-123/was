@extends('layouts.admin')

@section('title', $tip->exists ? __('Edit tip') : __('New tip'))

@section('content')
    <form method="POST" action="{{ $tip->exists ? route('admin.tech-tips.update', $tip) : route('admin.tech-tips.store') }}" novalidate id="tip-form">
        @csrf
        @if ($tip->exists) @method('PUT') @endif

        <div class="row g-3">
            {{-- Start screen + slides (TipEditor.vue) --}}
            <div class="col-xl-9 col-lg-8">
                @php($previewUrl = route('admin.tech-tips.preview', $tip->exists ? ['tip' => $tip->id] : []))
                <div data-vue="TipEditor" data-props='@json($editor + ['previewUrl' => $previewUrl], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)'>
                    <div class="card"><div class="card-body text-center py-5 text-body-secondary"><span class="spinner-border spinner-border-sm"></span></div></div>
                </div>
            </div>

            {{-- Settings of the whole tip --}}
            <div class="col-xl-3 col-lg-4">
                <div class="card position-sticky" style="top: 1rem">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="category" class="form-label">{{ __('Topic') }} <span class="text-danger">*</span></label>
                            <select id="category" name="category" required class="form-select @error('category') is-invalid @enderror">
                                @foreach (config('tips.categories') as $key => $category)
                                    <option value="{{ $key }}" @selected(old('category', $tip->category) === $key)>{{ __($category['label']) }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">{{ $errors->first('category') }}</div>
                        </div>
                        <div class="mb-3">
                            <label for="level" class="form-label">{{ __('Level') }}</label>
                            <select id="level" name="level" class="form-select @error('level') is-invalid @enderror">
                                <option value="">{{ __('For everyone') }}</option>
                                @foreach (config('tips.levels') as $value => $label)
                                    <option value="{{ $value }}" @selected((string) old('level', $tip->level) === (string) $value)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="sort_order" class="form-label">{{ __('Order') }}</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $tip->sort_order) }}"
                                   class="form-control @error('sort_order') is-invalid @enderror">
                            <div class="form-text">{{ __('Smaller numbers come first on the tablet.') }}</div>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="show_warning" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="show_warning" name="show_warning" value="1" @checked(old('show_warning', $tip->show_warning))>
                            <label class="form-check-label" for="show_warning">{{ __('Safety box at the top') }}</label>
                            <div class="form-text mt-0">{{ __('"These tips do not replace training with an instructor…"') }}</div>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input type="hidden" name="is_published" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_published" name="is_published" value="1" @checked(old('is_published', $tip->is_published))>
                            <label class="form-check-label" for="is_published">{{ __('Show on the tablet') }}</label>
                        </div>
                        @if ($tip->exists)
                            <hr>
                            <div class="small text-body-secondary">
                                <div><i class="bi bi-eye"></i> {{ __('Read on the tablet: :count times', ['count' => $tip->views]) }}</div>
                                <div><i class="bi bi-clock"></i> {{ __('Updated') }}: {{ $tip->updated_at->format('d.m.Y H:i') }}</div>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer d-grid gap-2">
                        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('Save') }}</button>
                        <button class="btn btn-outline-primary" name="then" value="edit"><i class="bi bi-floppy"></i> {{ __('Save and keep editing') }}</button>
                        <button class="btn btn-outline-dark" formaction="{{ $previewUrl }}" formtarget="tip-preview" formnovalidate>
                            <i class="bi bi-tablet-landscape"></i> {{ __('Preview on the tablet') }}
                        </button>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.tech-tips.index') }}" class="btn btn-outline-secondary flex-grow-1">{{ __('Cancel') }}</a>
                            @if ($tip->exists)
                                <button form="delete-tip" class="btn btn-outline-danger" title="{{ __('Delete') }}" onclick="return confirm(@js(__('Delete this tip in all languages?')))">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    @if ($tip->exists)
        <form id="delete-tip" method="POST" action="{{ route('admin.tech-tips.destroy', $tip) }}">@csrf @method('DELETE')</form>
    @endif
@endsection
