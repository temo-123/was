<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'personal_id' => ['nullable', 'string', 'max:32', 'regex:/^\d{11}$/', 'unique:users,personal_id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $user = User::create($data);
        $user->assignRole(config('permissions.default_role'));

        // Sends the verification email and links a matching visitor (see AppServiceProvider).
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        ActivityLog::record('auth.registered', $user);

        return redirect()->route('profile.show')->with('success', __('Welcome! Please check your email to verify your address.'));
    }
}
