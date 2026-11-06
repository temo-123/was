<?php

namespace App\Services\Tips;

use App\Services\Club\CheckinQr;
use Illuminate\Http\Request;

/** The address to type (or scan) on the gym tablet: http://<this computer's LAN IP>:<tips port>. */
class TabletUrl
{
    public function __construct(private CheckinQr $qr) {}

    public function url(Request $request): string
    {
        if ($configured = config('tips.url')) {
            return rtrim($configured, '/');
        }

        // A tablet can't open "localhost" or a name from this computer's hosts file, but it can open the LAN IP.
        $host = $this->qr->lanIp() ?? $request->getHost();
        $port = config('tips.port');

        return $port
            ? 'http://'.$host.($port === 80 ? '' : ':'.$port)
            : $request->getScheme().'://'.$host.$request->getBaseUrl().'/tips';
    }

    public function svg(string $url): string
    {
        return $this->qr->svg($url, 220);
    }
}
