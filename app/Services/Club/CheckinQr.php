<?php

namespace App\Services\Club;

use App\Models\ClubMeeting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;

/** Builds the check-in URL behind a meeting's QR code and renders the code as SVG. */
class CheckinQr
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]'];

    public function url(ClubMeeting $meeting, Request $request): string
    {
        $url = route('club.checkin', ['meeting' => $meeting->token, 'c' => $meeting->checkinCode()]);

        return $this->replaceBase($url, $this->baseUrl($request));
    }

    /** True when the admin opened the panel as "localhost" and no LAN address could be found: phones can't open it. */
    public function unreachable(Request $request): bool
    {
        return ! config('club.checkin_url')
            && in_array($request->getHost(), self::LOCAL_HOSTS, true)
            && $this->lanIp() === null;
    }

    /** "localhost" means nothing on a phone, so it is swapped for the laptop's address in the local network. */
    public function baseUrl(Request $request): ?string
    {
        if ($configured = config('club.checkin_url')) {
            return rtrim($configured, '/');
        }
        if (in_array($request->getHost(), self::LOCAL_HOSTS, true) && ($ip = $this->lanIp())) {
            $port = in_array($request->getPort(), [80, 443], true) ? '' : ':'.$request->getPort();

            return $request->getScheme().'://'.$ip.$port.$request->getBaseUrl();
        }

        return null; // keep the address the panel is opened with
    }

    public function svg(string $text, int $size = 360): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd)))->writeString($text);

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    /** IPv4 address of this computer in the local network (Wi-Fi / hotspot). */
    public function lanIp(): ?string
    {
        // A UDP "connection" sends nothing; it only makes the OS pick the outgoing interface.
        $socket = @stream_socket_client('udp://8.8.8.8:53', $code, $message, 1);
        if ($socket) {
            $ip = explode(':', (string) stream_socket_get_name($socket, false))[0];
            fclose($socket);
            if ($this->isLanIp($ip)) {
                return $ip;
            }
        }

        $ip = gethostbyname(gethostname());

        return $this->isLanIp($ip) ? $ip : null;
    }

    private function isLanIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ! str_starts_with($ip, '127.');
    }

    private function replaceBase(string $url, ?string $base): string
    {
        if (! $base) {
            return $url;
        }
        $path = substr($url, strlen(url('/')));

        return $base.$path;
    }
}
