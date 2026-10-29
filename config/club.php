<?php

return [

    /*
    | Address that phones use to open the check-in page behind the QR code, e.g.
    | http://192.168.1.10 (the laptop's IP in the local Wi-Fi). Leave empty to use the
    | address the admin panel is opened with; "localhost" is replaced by the detected LAN IP.
    */
    'checkin_url' => env('CLUB_CHECKIN_URL'),

    // The QR code changes every N seconds so a photo of it can't be sent to people who
    // aren't at the meeting. 0 = the code never changes.
    'qr_rotation_seconds' => (int) env('CLUB_QR_ROTATION', 30),

    // How long an already replaced QR code is still accepted (slow scanners, bad Wi-Fi).
    'qr_grace_seconds' => 120,

    // After a valid scan, the member has this many minutes to submit the check-in form.
    'checkin_minutes' => 15,

    /*
    | Wi-Fi the phones should join (the laptop's network / hotspot). Shown on the QR screen as a second
    | QR code that connects the phone in one tap, plus the name and password as text. Leave the name
    | empty to hide it. A meeting can use another network (meeting form → Wi-Fi).
    */
    'wifi' => [
        'name' => env('CLUB_WIFI_NAME'),
        'password' => env('CLUB_WIFI_PASSWORD'),
        'encryption' => env('CLUB_WIFI_ENCRYPTION', 'WPA'), // WPA (also WPA2/WPA3), WEP, or nopass
        'hidden' => (bool) env('CLUB_WIFI_HIDDEN', false), // the network doesn't broadcast its name
        'show_password' => (bool) env('CLUB_WIFI_SHOW_PASSWORD', true), // also print the password under the code
    ],
];
