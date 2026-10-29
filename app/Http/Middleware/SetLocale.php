<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uses the language chosen with the switcher (cookie), or APP_LOCALE. */
class SetLocale
{
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(self::COOKIE);
        if (is_string($locale) && array_key_exists($locale, config('app.locales'))) {
            app()->setLocale($locale);
        }
        Carbon::setLocale(app()->getLocale());

        return $next($request);
    }
}
