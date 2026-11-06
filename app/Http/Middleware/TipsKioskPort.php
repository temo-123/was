<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gym tablet opens the site on its own port (config tips.port, e.g. http://192.168.1.10:8090).
 * On that port only the tech tips are reachable: "/" and every other page lead to /tips,
 * so nobody can wander from the tablet into the admin panel or the login page.
 */
class TipsKioskPort
{
    public function handle(Request $request, Closure $next): Response
    {
        $port = config('tips.port');

        if ($port && (int) $request->getPort() === $port && ! $request->is('tips', 'tips/*')) {
            abort_unless($request->isMethod('GET') || $request->isMethod('HEAD'), 404);

            return redirect()->route('tips.index');
        }

        return $next($request);
    }
}
