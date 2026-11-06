<?php

return [

    /*
    | The tablet in the gym opens the tips on their own port: http://<computer's LAN IP>:<port>.
    | Apache listens on it too (deploy/apache/*.conf), or run `php artisan serve --host=0.0.0.0 --port=8090`.
    | Requests on this port only reach the tips page: everything else (admin, login…) redirects to it.
    | Empty = no separate port; the page is still at /tips on the normal address.
    */
    'port' => (int) env('TIPS_PORT', 8090) ?: null,

    // Address shown in the admin panel (with a QR code) for opening the page on the tablet, e.g.
    // http://192.168.1.10:8090. Empty = the detected LAN IP + the port above.
    'url' => env('TIPS_URL'),

    // With nobody touching the tablet for this many seconds it goes back to the start screen.
    'idle_seconds' => (int) env('TIPS_IDLE_SECONDS', 90),

    // How often the tablet checks for new / edited articles.
    'refresh_seconds' => 300,

    // Topics (key => label, Bootstrap icon, accent colour on the tablet). Labels are translated in lang/ka.json.
    'categories' => [
        'technique' => ['label' => 'Technique', 'icon' => 'bi-hand-index-thumb', 'color' => '#ff7a1a'],
        'bouldering' => ['label' => 'Bouldering', 'icon' => 'bi-bricks', 'color' => '#f5b83d'],
        'belaying' => ['label' => 'Belaying & ropes', 'icon' => 'bi-link-45deg', 'color' => '#3db8f5'],
        'safety' => ['label' => 'Safety', 'icon' => 'bi-shield-check', 'color' => '#36c47a'],
        'training' => ['label' => 'Training', 'icon' => 'bi-lightning-charge', 'color' => '#a77bff'],
        'health' => ['label' => 'Warm-up & injuries', 'icon' => 'bi-heart-pulse', 'color' => '#ff5d73'],
        'gear' => ['label' => 'Gear', 'icon' => 'bi-backpack', 'color' => '#2fd1c5'],
        'etiquette' => ['label' => 'Gym etiquette', 'icon' => 'bi-people', 'color' => '#c9d16b'],
    ],

    'levels' => [
        1 => 'Beginner',
        2 => 'Intermediate',
        3 => 'Advanced',
    ],

    // Uploaded pictures are shrunk to this width (px) so the tablet loads them fast over Wi-Fi.
    'image_max_width' => 1600,

    // Biggest video upload (MB). PHP must allow it too: upload_max_filesize and post_max_size in php.ini
    // (XAMPP: C:\xampp\php\php.ini, default 40M), then restart Apache.
    'video_max_mb' => (int) env('TIPS_VIDEO_MAX_MB', 200),
];
