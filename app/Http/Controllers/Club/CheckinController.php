<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClubMemberRequest;
use App\Models\ActivityLog;
use App\Models\ClubAttendance;
use App\Models\ClubMeeting;
use App\Models\ClubMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Public page opened by scanning the meeting QR code. No login: the member types their full name, phone,
 * email or ID number. The page only says whether they were found (it never shows anybody's data) and checks
 * them in with one tap, or lets them register as a new member (an admin approves them later).
 * The phone remembers them for the next meetings. A member whose card is incomplete (added by an admin with only
 * a name and phone, for example) is asked for the missing details right after checking in.
 */
class CheckinController extends Controller
{
    private const COOKIE = 'club_member';

    public function show(Request $request, ClubMeeting $meeting): View|RedirectResponse
    {
        // A fresh code from the QR → allow checking in for a few minutes, then drop the code from the URL.
        if ($request->has('c')) {
            if ($meeting->isValidCheckinCode($request->query('c'))) {
                $request->session()->put($this->sessionKey($meeting), now()->addMinutes(config('club.checkin_minutes'))->timestamp);
            }

            return redirect()->route('club.checkin', $meeting);
        }

        $allowed = $this->allowed($request, $meeting);
        $member = $this->rememberedMember($request);

        // Search (GET form) by full name, phone, email or ID number: null = not searched yet.
        // Only the number of matches reaches the view (0, 1, or 2 = "more than one").
        $search = null;
        $matches = null;
        if ($allowed && $meeting->is_open && $request->has('q')) {
            $validator = Validator::make($request->only('q'), $this->searchRules());
            if ($validator->fails()) {
                return redirect()->route('club.checkin', $meeting)->withErrors($validator)->withInput();
            }
            $search = trim($request->query('q'));
            $matches = $this->matches($search);
        }

        return view('club.checkin', [
            'meeting' => $meeting,
            'allowed' => $allowed,
            'member' => $member,
            'attendance' => $member ? $meeting->attendances()->where('club_member_id', $member->id)->first() : null,
            'canCompleteProfile' => $member && $this->profileMember($request, $meeting)?->is($member) && $member->missingProfileFields(),
            'search' => $search,
            'found' => $matches?->count(),
            'prefill' => $search !== null ? $this->prefill($search) : [],
        ]);
    }

    public function store(Request $request, ClubMeeting $meeting): RedirectResponse
    {
        if (! $meeting->is_open || ! $this->allowed($request, $meeting)) {
            return redirect()->route('club.checkin', $meeting);
        }

        if ($request->boolean('new')) {
            $data = ClubMemberRequest::normalize($request->only(ClubMember::PROFILE_FIELDS));
            $validator = Validator::make($data, ClubMemberRequest::profileRules(), $this->registrationMessages(), ClubMemberRequest::fieldAttributes());
            if ($validator->fails()) {
                return redirect()->route('club.checkin', [$meeting, 'q' => $request->input('q')])->withErrors($validator)->withInput();
            }
            $member = $this->registerMember($validator->validated(), $meeting);
        } else {
            $member = match (true) {
                $request->boolean('remembered') => $this->rememberedMember($request),
                $request->boolean('found') => $this->foundMember($request),
                default => null,
            };
        }

        if (! $member) {
            return redirect()->route('club.checkin', $meeting)->withErrors(['q' => __('Please search for yourself again.')]);
        }

        $attendance = ClubAttendance::firstOrCreate(
            ['club_meeting_id' => $meeting->id, 'club_member_id' => $member->id],
            ['checked_in_at' => now(), 'method' => 'qr', 'ip_address' => $request->ip()],
        );

        cookie()->queue(cookie()->forever(self::COOKIE, (string) $member->id));

        $message = $attendance->wasRecentlyCreated
            ? __('Thank you, :name! You are checked in.', ['name' => $member->first_name])
            : __(':name, you were already checked in at :time.', ['name' => $member->first_name, 'time' => $attendance->checked_in_at->format('H:i')]);

        // Something is missing on the member card → ask for it now, while they are holding the phone.
        if ($member->missingProfileFields()) {
            $request->session()->put($this->profileKey($meeting), ['member' => $member->id, 'until' => now()->addMinutes(config('club.checkin_minutes'))->timestamp]);

            return redirect()->route('club.checkin.profile', $meeting)->with('checked_in', $message);
        }

        return redirect()->route('club.checkin', $meeting)->with('checked_in', $message);
    }

    /** "Complete your details": only the empty fields of the member who has just checked in on this phone. */
    public function profile(Request $request, ClubMeeting $meeting): View|RedirectResponse
    {
        $member = $this->profileMember($request, $meeting);
        if (! $member || ! $member->missingProfileFields()) {
            return redirect()->route('club.checkin', $meeting);
        }

        return view('club.profile', ['meeting' => $meeting, 'member' => $member, 'missing' => $member->missingProfileFields()]);
    }

    public function updateProfile(Request $request, ClubMeeting $meeting): RedirectResponse
    {
        $member = $this->profileMember($request, $meeting);
        if (! $member) {
            return redirect()->route('club.checkin', $meeting);
        }

        // Filled-in fields can't be changed here (only an admin can), so nobody can overwrite someone else's card.
        $missing = $member->missingProfileFields();
        $data = ClubMemberRequest::normalize($request->only($missing));
        $validator = Validator::make($data, ClubMemberRequest::profileRules($member->id, $missing), ClubMemberRequest::fieldMessages(), ClubMemberRequest::fieldAttributes());
        if ($validator->fails()) {
            return redirect()->route('club.checkin.profile', $meeting)->withErrors($validator)->withInput();
        }

        $member->update($validator->validated());
        ActivityLog::record('club_member.profile_completed', $member, ['meeting' => $meeting->id, 'fields' => $missing]);
        $request->session()->forget($this->profileKey($meeting));

        return redirect()->route('club.checkin', $meeting)->with('checked_in', __('Thank you, :name! Your details are saved.', ['name' => $member->first_name]));
    }

    /** "Not me" on a shared phone. */
    public function forget(Request $request, ClubMeeting $meeting): RedirectResponse
    {
        cookie()->queue(cookie()->forget(self::COOKIE));

        return redirect()->route('club.checkin', $meeting);
    }

    /** "Check me in" after a search: the search is run again and must find exactly one member. */
    private function foundMember(Request $request): ?ClubMember
    {
        if (Validator::make($request->only('q'), $this->searchRules())->fails()) {
            return null;
        }
        $matches = $this->matches(trim($request->input('q')));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** "I'm not registered yet": creates a member waiting for admin approval. */
    private function registerMember(array $data, ClubMeeting $meeting): ClubMember
    {
        $member = ClubMember::create($data + ['is_active' => true, 'is_pending' => true]);
        ActivityLog::record('club_member.self_registered', $member, ['meeting' => $meeting->id]);

        return $member;
    }

    private function matches(string $term): Collection
    {
        return ClubMember::where('is_active', true)
            ->checkinMatch($term)
            ->limit(2) // enough to tell "one member" from "more than one"
            ->get();
    }

    /** Puts what was searched into the matching field of the registration form. */
    private function prefill(string $term): array
    {
        $digits = preg_replace('/[\s\-().+]/', '', $term);

        return match (true) {
            str_contains($term, '@') => ['email' => $term],
            ctype_digit($digits) && strlen($digits) === 11 => ['personal_id' => $digits],
            ctype_digit($digits) => ['phone' => $digits],
            default => array_combine(['first_name', 'last_name'], array_pad(preg_split('/\s+/', $term, 2), 2, null)),
        };
    }

    private function searchRules(): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:255']];
    }

    /** Self-registration: every field is required, and the ID number, phone and email must be new. */
    private function registrationMessages(): array
    {
        $registered = fn (string $field) => __('You are already registered with this :field — search with it above and check in.', ['field' => __($field)]);

        return [
            'personal_id.unique' => $registered('ID number'),
            'phone.unique' => $registered('phone number'),
            'email.unique' => $registered('email'),
        ] + ClubMemberRequest::fieldMessages();
    }

    private function allowed(Request $request, ClubMeeting $meeting): bool
    {
        if (config('club.qr_rotation_seconds') === 0) {
            return true; // static QR code: the link itself is enough
        }

        return (int) $request->session()->get($this->sessionKey($meeting)) >= now()->timestamp;
    }

    private function rememberedMember(Request $request): ?ClubMember
    {
        $id = $request->cookie(self::COOKIE);

        return $id ? ClubMember::where('is_active', true)->find($id) : null;
    }

    /** The member who checked in on this phone and may still fill in their missing details. */
    private function profileMember(Request $request, ClubMeeting $meeting): ?ClubMember
    {
        $grant = $request->session()->get($this->profileKey($meeting));
        if (! is_array($grant) || ($grant['until'] ?? 0) < now()->timestamp) {
            return null;
        }

        return ClubMember::where('is_active', true)->find($grant['member'] ?? null);
    }

    private function profileKey(ClubMeeting $meeting): string
    {
        return 'club_profile.'.$meeting->id;
    }

    private function sessionKey(ClubMeeting $meeting): string
    {
        return 'club_checkin.'.$meeting->id;
    }
}
