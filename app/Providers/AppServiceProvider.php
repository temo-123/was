<?php

namespace App\Providers;

use App\Models\User;
use App\Services\VisitorLinker;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Password::defaults(fn () => app()->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8));

        // Every permission name (e.g. "visitors.create") works as a gate:
        // @can('visitors.create'), $user->can('visitors.create'), ->middleware('can:visitors.create').
        Gate::before(function (User $user, string $ability) {
            if (! $user->is_active) {
                return false;
            }

            return $user->hasPermission($ability) ?: null;
        });

        // Connect new accounts with existing visitor records.
        Event::listen(Registered::class, fn (Registered $event) => app(VisitorLinker::class)->linkUserToMatchingVisitor($event->user));
        Event::listen(Verified::class, fn (Verified $event) => app(VisitorLinker::class)->linkUserToMatchingVisitor($event->user));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
