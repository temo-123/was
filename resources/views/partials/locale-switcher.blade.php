{{-- Language switcher for a navbar: <li> items, the current language highlighted. --}}
<li class="nav-item d-flex align-items-center me-md-2 my-2 my-md-0">
    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Language') }}">
        @foreach (config('app.locales') as $code => $name)
            <a href="{{ route('locale', $code) }}" lang="{{ $code }}" title="{{ $name }}" hreflang="{{ $code }}"
               @class(['btn', 'btn-secondary active' => app()->getLocale() === $code, 'btn-outline-secondary' => app()->getLocale() !== $code])
               @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $code === 'ka' ? 'ქარ' : strtoupper($code) }}</a>
        @endforeach
    </div>
</li>
