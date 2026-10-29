<?php

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisitorRequest;
use App\Models\ActivityLog;
use App\Models\ClubAttendance;
use App\Models\ClubMeeting;
use App\Models\ClubMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Public page opened by scanning the meeting QR code. No login: the member types their name, phone,
 * email or ID number, picks themselves from the matches, or registers as a new member (an admin approves them later).
 * The phone remembers them for the next meetings.
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

        // Search (GET form) by name, phone, email or ID number: null = not searched yet.
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
            'search' => $search,
            'matches' => $matches,
            'prefill' => $search !== null ? $this->prefill($search) : [],
        ]);
    }

    public function store(Request $request, ClubMeeting $meeting): RedirectResponse
    {
        if (! $meeting->is_open || ! $this->allowed($request, $meeting)) {
            return redirect()->route('club.checkin', $meeting);
        }

        if ($request->boolean('new')) {
            $data = VisitorRequest::normalize($request->only('first_name', 'last_name', 'personal_id', 'phone', 'email'));
            $validator = Validator::make($data, $this->registrationRules(), $this->registrationMessages());
            if ($validator->fails()) {
                return redirect()->route('club.checkin', [$meeting, 'q' => $request->input('q')])->withErrors($validator)->withInput();
            }
            $member = $this->registerMember($validator->validated(), $meeting);
        } else {
            $member = match (true) {
                $request->boolean('remembered') => $this->rememberedMember($request),
                $request->filled('member_id') => $this->pickedMember($request),
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

        return redirect()->route('club.checkin', $meeting)->with('checked_in', $attendance->wasRecentlyCreated
            ? __('Thank you, :name! You are checked in.', ['name' => $member->first_name])
            : __(':name, you were already checked in at :time.', ['name' => $member->first_name, 'time' => $attendance->checked_in_at->format('H:i')]));
    }

    /** "Not me" on a shared phone. */
    public function forget(Request $request, ClubMeeting $meeting): RedirectResponse
    {
        cookie()->queue(cookie()->forget(self::COOKIE));

        return redirect()->route('club.checkin', $meeting);
    }

    /** The member chosen from the search results; the search is run again so any ID can't be posted. */
    private function pickedMember(Request $request): ?ClubMember
    {
        if (Validator::make($request->only('q'), $this->searchRules())->fails()) {
            return null;
        }

        return $this->matches(trim($request->input('q')))->firstWhere('id', $request->integer('member_id'));
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
            ->orderBy('is_pending')->orderBy('last_name')->orderBy('first_name')
            ->limit(10)
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
    private function registrationRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:100', 'regex:'.VisitorRequest::NAME_REGEX],
            'last_name' => ['required', 'string', 'min:2', 'max:100', 'regex:'.VisitorRequest::NAME_REGEX],
            'personal_id' => ['required', 'string', 'regex:'.VisitorRequest::PERSONAL_ID_REGEX, Rule::unique('club_members', 'personal_id')],
            'phone' => ['required', 'string', 'regex:'.VisitorRequest::PHONE_REGEX, Rule::unique('club_members', 'phone')],
            'email' => ['required', 'string', 'max:255', 'email:rfc,strict', 'regex:'.VisitorRequest::EMAIL_REGEX, Rule::unique('club_members', 'email')],
        ];
    }

    private function registrationMessages(): array
    {
        $registered = fn (string $field) => __('You are already registered with this :field — search for it above and tap your name.', ['field' => __($field)]);

        return VisitorRequest::fieldMessages() + [
            'personal_id.unique' => $registered('ID number'),
            'phone.unique' => $registered('phone number'),
            'email.unique' => $registered('email'),
        ];
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

    private function sessionKey(ClubMeeting $meeting): string
    {
        return 'club_checkin.'.$meeting->id;
    }
}
