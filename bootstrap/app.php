<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lets the Vue components in the admin panel call /api/* with the session cookie.
        $middleware->statefulApi();

        // A tunnel (cloudflared / ngrok) running on this computer forwards https requests as http:
        // trust its X-Forwarded-* headers so links, forms and assets use https and the public host.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->alias(['active' => EnsureUserIsActive::class]);
        $middleware->web(append: [SetLocale::class, EnsureUserIsActive::class]);
        $middleware->api(append: [SetLocale::class]); // Vue → /api: validation messages in the chosen language
        $middleware->encryptCookies(except: [SetLocale::COOKIE]); // just "ka" / "en", readable by the API too

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->canAccessAdmin()
            ? route('admin.dashboard')
            : route('profile.show'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
