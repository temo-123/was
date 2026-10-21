<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\VisitorLinker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(VisitorLinker $linker): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => __('Google sign in failed. Please try again.')]);
        }

        if (! $google->getEmail()) {
            return redirect()->route('login')->withErrors(['email' => __('Your Google account has no email address.')]);
        }

        // 1) Already connected  2) same email -> connect Google to that account  3) new account
        $user = User::withTrashed()->where('google_id', $google->getId())->first()
            ?? User::withTrashed()->where('email', $google->getEmail())->first();

        if ($user?->trashed() || ($user && ! $user->is_active)) {
            return redirect()->route('login')->withErrors(['email' => __('Your account is disabled. Please contact the gym.')]);
        }

        $isNew = ! $user;
        if ($isNew) {
            $user = User::create([
                'name' => $google->getName() ?: $google->getEmail(),
                'email' => $google->getEmail(),
            ]);
            $user->assignRole(config('permissions.default_role'));
        }

        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar' => $user->avatar ?: $google->getAvatar(),
            // Google has verified this address.
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        $linker->linkUserToMatchingVisitor($user);

        Auth::login($user, remember: true);
        request()->session()->regenerate();
        ActivityLog::record($isNew ? 'auth.registered_google' : 'auth.login_google', $user);

        return redirect()->intended(LoginController::homeFor($user));
    }
}
