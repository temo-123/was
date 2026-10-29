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
use Illuminate\View\View;

/**
 * Public page opened by scanning the meeting QR code. No login: the member types their name,
 * picks themselves from the matches, or registers as a new member (an admin approves them later).
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

        // Name search (GET form): null = not searched yet.
        $search = null;
        $matches = null;
        if ($allowed && $meeting->is_open && $request->hasAny(['first_name', 'last_name'])) {
            $search = VisitorRequest::normalize($request->only('first_name', 'last_name'));
            $validator = Validator::make($search, $this->nameRules());
            if ($validator->fails()) {
                return redirect()->route('club.checkin', $meeting)->withErrors($validator)->withInput();
            }
            $matches = $this->matches($search['first_name'], $search['last_name']);
        }

        return view('club.checkin', [
            'meeting' => $meeting,
            'allowed' => $allowed,
            'member' => $member,
            'attendance' => $member ? $meeting->attendances()->where('club_member_id', $member->id)->first() : null,
            'search' => $search,
            'matches' => $matches,
        ]);
    }

    public function store(Request $request, ClubMeeting $meeting): RedirectResponse
    {
        if (! $meeting->is_open || ! $this->allowed($request, $meeting)) {
            return redirect()->route('club.checkin', $meeting);
        }

        $member = match (true) {
            $request->boolean('remembered') => $this->rememberedMember($request),
            $request->filled('member_id') => $this->pickedMember($request),
            $request->boolean('new') => $this->registerMember($request, $meeting),
            default => null,
        };

        if (! $member) {
            return redirect()->route('club.checkin', $meeting)->withErrors(['first_name' => __('Please search for your name again.')]);
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

    /** The member chosen from the search results; the name is checked again so any ID can't be posted. */
    private function pickedMember(Request $request): ?ClubMember
    {
        $data = VisitorRequest::normalize($request->only('first_name', 'last_name'));
        if (Validator::make($data, $this->nameRules())->fails()) {
            return null;
        }

        return $this->matches($data['first_name'], $data['last_name'])->firstWhere('id', $request->integer('member_id'));
    }

    /** "I'm not registered yet": creates a member waiting for admin approval (or reuses one with the same phone). */
    private function registerMember(Request $request, ClubMeeting $meeting): ?ClubMember
    {
        $data = VisitorRequest::normalize($request->only('first_name', 'last_name', 'phone'));
        $validator = Validator::make($data, $this->nameRules() + [
            'phone' => ['nullable', 'string', 'regex:'.VisitorRequest::PHONE_REGEX],
        ], VisitorRequest::fieldMessages());
        if ($validator->fails()) {
            return null;
        }

        if (! empty($data['phone']) && ($existing = ClubMember::where('is_active', true)->where('phone', $data['phone'])->first())) {
            return $existing;
        }

        $member = ClubMember::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
            'is_pending' => true,
        ]);
        ActivityLog::record('club_member.self_registered', $member, ['meeting' => $meeting->id]);

        return $member;
    }

    private function matches(string $firstName, string $lastName): Collection
    {
        return ClubMember::where('is_active', true)
            ->nameMatch($firstName, $lastName)
            ->orderBy('is_pending')->orderBy('last_name')->orderBy('first_name')
            ->limit(10)
            ->get();
    }

    private function nameRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:100', 'regex:'.VisitorRequest::NAME_REGEX],
            'last_name' => ['required', 'string', 'min:2', 'max:100', 'regex:'.VisitorRequest::NAME_REGEX],
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
