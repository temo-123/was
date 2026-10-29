<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meeting->title }} · {{ __('Check-in') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/sass/app.scss'])
    <style>
        html, body { height: 100%; }
        body { background: linear-gradient(135deg, #eef4ff 0%, #f7f9fc 50%, #eafaf1 100%); }
        .screen { min-height: 100%; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 3vw; padding: 3vh 3vw; }
        .side { align-self: center; }
        .qr-card { background: #fff; border-radius: 2rem; padding: 1.5rem; box-shadow: 0 1rem 3rem rgba(13, 38, 76, .12); text-align: center; }
        #qr svg { width: min(62vh, 42vw); height: auto; display: block; margin: 0 auto; }
        .step { display: flex; gap: .9rem; align-items: center; margin-bottom: 1.1rem; font-size: 1.25rem; }
        .step-num { flex: none; width: 2.6rem; height: 2.6rem; border-radius: 50%; background: var(--bs-primary); color: #fff;
            display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .count { font-size: clamp(4rem, 9vw, 8rem); font-weight: 700; line-height: 1; color: var(--bs-success); }
        #qr-latest li { background: #fff; border-radius: 1rem; padding: .55rem .9rem; margin-bottom: .5rem; font-size: 1.15rem;
            box-shadow: 0 .25rem .75rem rgba(13, 38, 76, .06); display: flex; justify-content: space-between; gap: .5rem; }
        #qr-latest li.new { animation: pop .6s ease-out; }
        @keyframes pop { from { transform: scale(.9); opacity: 0; background: #d1f7e0; } to { transform: scale(1); opacity: 1; } }
        #fullscreen { position: fixed; top: .75rem; right: .75rem; opacity: .35; }
        #fullscreen:hover { opacity: 1; }
        :fullscreen #fullscreen { display: none; }
        @media (max-width: 900px) {
            .screen { grid-template-columns: 1fr; text-align: center; }
            .side-left { order: -1; }
            #qr svg { width: min(80vw, 60vh); }
            .step { justify-content: center; }
        }
    </style>
</head>
<body data-bs-theme="light">
<button type="button" class="btn btn-sm btn-outline-secondary" id="fullscreen" title="{{ __('Full screen') }}"><i class="bi bi-arrows-fullscreen"></i></button>

<main class="screen">
    {{-- Left: meeting and how to check in --}}
    <section class="side side-left">
        <div class="text-primary fw-semibold text-uppercase small mb-1"><i class="bi bi-people-fill"></i> {{ __('Club meeting') }}</div>
        <h1 class="fw-bold mb-2">{{ $meeting->title }}</h1>
        <p class="fs-5 text-body-secondary mb-4">
            <i class="bi bi-calendar-event"></i> {{ $meeting->starts_at->format('d.m.Y H:i') }}
            @if ($meeting->location)<br><i class="bi bi-geo-alt"></i> {{ $meeting->location }}@endif
        </p>

        <div class="step"><span class="step-num">1</span> {{ __('Open your phone camera') }}</div>
        <div class="step"><span class="step-num">2</span> {{ __('Point it at the QR code') }}</div>
        <div class="step"><span class="step-num">3</span> {{ __('Find yourself by name, phone, email or ID number') }}</div>

        @if ($unreachable || $localhost)
            <div class="alert alert-warning small mt-4 mb-0">
                <i class="bi bi-wifi-off"></i>
                {{ __('Phones cannot open "localhost". Connect the phones to the same Wi-Fi (or the laptop hotspot) and set CLUB_CHECKIN_URL in .env to this laptop\'s address, e.g. http://192.168.1.10') }}
            </div>
        @endif
    </section>

    {{-- Center: QR code --}}
    <section class="qr-card">
        <div id="qr-closed" class="p-5 text-body-secondary" @if ($meeting->is_open) hidden @endif>
            <i class="bi bi-lock display-1 d-block mb-3"></i>
            <div class="fs-2 fw-semibold">{{ __('Registration is closed') }}</div>
            <div class="fs-5">{{ __('The meeting is finished.') }}</div>
        </div>
        <div id="qr-open" @unless ($meeting->is_open) hidden @endunless>
            <div id="qr">{!! $svg !!}</div>
            <div class="fs-4 fw-semibold mt-2"><i class="bi bi-qr-code-scan text-primary"></i> {{ __('Scan to check in') }}</div>
        </div>
    </section>

    {{-- Right: who is here --}}
    <section class="side">
        <div class="count" id="qr-count">{{ $count }}</div>
        <div class="fs-4 text-body-secondary mb-3">{{ __('checked in') }}</div>
        <ul class="list-unstyled mb-0" id="qr-latest">
            @foreach ($latest as $item)
                <li><span><i class="bi bi-check-circle-fill text-success"></i> {{ $item['name'] }}</span> <small class="text-body-secondary">{{ $item['time'] }}</small></li>
            @endforeach
        </ul>
    </section>
</main>

<script>
    (() => {
        const pollUrl = @js(route('admin.club.meetings.qr', $meeting));
        const $ = (id) => document.getElementById(id);
        let shown = new Set(@js($latest->pluck('name')));

        const escape = (text) => text.replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

        async function refresh() {
            try {
                const response = await fetch(pollUrl, {headers: {Accept: 'application/json'}});
                if (!response.ok) return;
                const data = await response.json();
                $('qr').innerHTML = data.svg ?? '';
                $('qr-open').hidden = !data.is_open;
                $('qr-closed').hidden = data.is_open;
                if (!data.is_open) clearInterval(timer); // finished: the list won't change any more
                $('qr-count').textContent = data.count;
                // Names that just arrived get a small "pop" animation.
                $('qr-latest').innerHTML = data.latest.map((item) =>
                    `<li class="${shown.has(item.name) ? '' : 'new'}"><span><i class="bi bi-check-circle-fill text-success"></i> ${escape(item.name)}</span> <small class="text-body-secondary">${item.time}</small></li>`).join('');
                shown = new Set(data.latest.map((item) => item.name));
            } catch (e) {
                // offline for a moment: keep the old code on screen
            }
        }

        // The QR code changes every {{ $rotation }} s; the attendee list is refreshed more often.
        const timer = setInterval(refresh, 5000);

        $('fullscreen').addEventListener('click', () => document.documentElement.requestFullscreen?.());
    })();
</script>
</body>
</html>
