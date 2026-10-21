<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\VisitorLinker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('roles', 'visitor.category');
        $visitor = $user->visitor;

        return view('profile.show', [
            'user' => $user,
            'visitor' => $visitor,
            'visits' => $visitor?->visits()->with('ticketType', 'timeSlot')->latest('entered_at')->limit(20)->get() ?? collect(),
            'subscriptions' => $visitor?->subscriptions()->with('ticketType', 'timeSlot')->latest('ends_on')->limit(10)->get() ?? collect(),
        ]);
    }

    public function update(Request $request, VisitorLinker $linker): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'personal_id' => ['nullable', 'string', 'max:32', 'regex:/^\d{11}$/', Rule::unique('users')->ignore($user->id)],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ]);

        // Personal ID is locked once the account is linked to a visitor card.
        if ($user->visitorLink()->exists()) {
            unset($data['personal_id']);
        }

        $user->fill($data);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        $linker->linkUserToMatchingVisitor($user);

        return back()->with('success', __('Profile updated.'));
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            // Accounts created with Google have no password yet; they can set one without the current one.
            'current_password' => [$user->hasPassword() ? 'required' : 'nullable', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill(['password' => Hash::make($request->string('password'))])->save();
        ActivityLog::record('auth.password_changed', $user);

        return back()->with('success', __('Password updated.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'confirm_email' => ['required', 'in:'.$user->email],
        ], ['confirm_email.in' => __('Type your email address to confirm.')]);

        ActivityLog::record('auth.account_deleted', $user);
        $user->visitorLink()->delete();
        $user->tokens()->delete();

        Auth::logout();
        // Self-deletion removes the account for good (visit history stays on the visitor record).
        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', __('Your account has been deleted.'));
    }
}
