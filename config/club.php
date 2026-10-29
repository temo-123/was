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
];
