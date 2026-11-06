<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0e1115">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="robots" content="noindex">
    <title>{{ __('Tech tips') }} · {{ config('app.name') }}</title>
    {{-- The gym network may have no internet: the page also looks right with the tablet's own fonts. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/sass/tips.scss', 'resources/js/tips.js'])
</head>
<body>
{{-- Unescaped Georgian keeps the page half the size; HEX_* flags keep the attribute safe like plain @json. --}}
<div data-vue="TechTips" data-props='@json($props, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)'>
    <div class="tt-splash"><i class="bi bi-triangle-fill"></i></div>
</div>
<noscript><p style="padding:2rem;color:#fff">{{ __('Please turn on JavaScript in the browser.') }}</p></noscript>
</body>
</html>
