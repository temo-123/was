<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    /** Language switcher: remembers the choice for a year and goes back to the same page. */
    public function __invoke(string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('app.locales')), 404);

        return redirect()->back(fallback: route('home'))->withCookie(cookie(SetLocale::COOKIE, $locale, 60 * 24 * 365));
    }
}
