<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorUser;
use Illuminate\Validation\ValidationException;

/**
 * Connects registered user accounts with visitor records (visitor_user table).
 *
 * Automatic matching is done by personal ID number, or by email when the
 * user's email address is verified (so nobody can claim someone else's card
 * just by typing their email).
 */
class VisitorLinker
{
    /** Called after registration / Google login / email verification. */
    public function linkUserToMatchingVisitor(User $user): ?VisitorUser
    {
        if (VisitorUser::where('user_id', $user->id)->exists()) {
            return null;
        }

        $unlinked = Visitor::whereDoesntHave('userLink');

        if ($user->personal_id && $visitor = (clone $unlinked)->where('personal_id', $user->personal_id)->first()) {
            return $this->link($visitor, $user, 'personal_id');
        }

        if ($user->hasVerifiedEmail()) {
            $matches = (clone $unlinked)->where('email', $user->email)->limit(2)->get();
            if ($matches->count() === 1) {
                return $this->link($matches->first(), $user, 'email');
            }
        }

        return null;
    }

    /** Called after a visitor is created or edited by staff. */
    public function linkVisitorToMatchingUser(Visitor $visitor): ?VisitorUser
    {
        if (VisitorUser::where('visitor_id', $visitor->id)->exists()) {
            return null;
        }

        $unlinked = User::whereDoesntHave('visitorLink');

        if ($user = (clone $unlinked)->where('personal_id', $visitor->personal_id)->first()) {
            return $this->link($visitor, $user, 'personal_id');
        }

        if ($visitor->email && $user = (clone $unlinked)->where('email', $visitor->email)->whereNotNull('email_verified_at')->first()) {
            return $this->link($visitor, $user, 'email');
        }

        return null;
    }

    public function link(Visitor $visitor, User $user, string $via = 'manual', ?User $by = null): VisitorUser
    {
        if (VisitorUser::where('visitor_id', $visitor->id)->exists()) {
            throw ValidationException::withMessages(['visitor' => __('This visitor is already linked to a user account.')]);
        }
        if (VisitorUser::where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['user' => __('This user is already linked to a visitor.')]);
        }

        $link = VisitorUser::create([
            'visitor_id' => $visitor->id,
            'user_id' => $user->id,
            'linked_via' => $via,
            'linked_by' => $by?->id,
        ]);

        // Fill in profile details the user hasn't provided yet.
        $user->forceFill(array_filter([
            'personal_id' => $user->personal_id ?: (User::where('personal_id', $visitor->personal_id)->exists() ? null : $visitor->personal_id),
            'birth_date' => $user->birth_date ?: $visitor->birth_date,
            'phone' => $user->phone ?: $visitor->phone,
        ]))->save();

        ActivityLog::record('visitor.linked', $visitor, ['user_id' => $user->id, 'via' => $via]);

        return $link;
    }

    public function unlink(Visitor $visitor): void
    {
        $link = VisitorUser::where('visitor_id', $visitor->id)->first();
        if ($link) {
            ActivityLog::record('visitor.unlinked', $visitor, ['user_id' => $link->user_id]);
            $link->delete();
        }
    }
}
