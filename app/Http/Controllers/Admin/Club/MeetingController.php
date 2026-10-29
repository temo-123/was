<?php

namespace App\Http\Controllers\Admin\Club;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ClubAttendance;
use App\Models\ClubMeeting;
use App\Models\ClubMember;
use App\Services\Club\CheckinQr;
use App\Services\Club\MemberSpreadsheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MeetingController extends Controller
{
    /** Meetings by date: newest first (or oldest), optionally only a date range or a title. */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', Rule::in(['newest', 'oldest'])],
        ]);

        $meetings = ClubMeeting::withCount('attendances')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('starts_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('starts_at', '<=', $to))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('location', 'like', $like));
            })
            ->orderBy('starts_at', ($filters['order'] ?? 'newest') === 'oldest' ? 'asc' : 'desc')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.club.meetings.index', [
            'meetings' => $meetings,
            // The page's meetings under month headings ("October 2026").
            'months' => $meetings->getCollection()->groupBy(fn (ClubMeeting $m) => $m->starts_at->format('Y-m')),
            'activeMembers' => ClubMember::where('is_active', true)->count(),
            'filtered' => array_filter($filters) !== [] && array_keys(array_filter($filters)) !== ['order'],
        ]);
    }

    public function create(): View
    {
        return view('admin.club.meetings.form', ['meeting' => new ClubMeeting(['starts_at' => now()->startOfHour(), 'is_open' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $meeting = ClubMeeting::create($this->validated($request) + ['is_open' => true, 'created_by' => $request->user()->id]);
        ActivityLog::record('club_meeting.created', $meeting);

        return redirect()->route('admin.club.meetings.show', $meeting)->with('success', __('Meeting created.'));
    }

    public function show(ClubMeeting $meeting): View
    {
        $attendances = $meeting->attendances()->with('member', 'creator')->orderBy('checked_in_at')->get();

        return view('admin.club.meetings.show', [
            'meeting' => $meeting,
            'attendances' => $attendances,
            'absent' => ClubMember::where('is_active', true)
                ->whereNotIn('id', $attendances->pluck('club_member_id'))
                ->orderBy('last_name')->orderBy('first_name')
                ->get(),
        ]);
    }

    public function edit(ClubMeeting $meeting): View
    {
        return view('admin.club.meetings.form', ['meeting' => $meeting]);
    }

    public function update(Request $request, ClubMeeting $meeting): RedirectResponse
    {
        $meeting->update($this->validated($request));
        ActivityLog::record('club_meeting.updated', $meeting, ['changes' => array_keys($meeting->getChanges())]);

        return redirect()->route('admin.club.meetings.show', $meeting)->with('success', __('Meeting updated.'));
    }

    public function destroy(ClubMeeting $meeting): RedirectResponse
    {
        $meeting->delete();
        ActivityLog::record('club_meeting.deleted', $meeting, ['title' => $meeting->title]);

        return redirect()->route('admin.club.meetings.index')->with('success', __('Meeting deleted.'));
    }

    /** Finishes the meeting for good: the QR code stops working and nobody can be added any more. */
    public function close(ClubMeeting $meeting): RedirectResponse
    {
        if ($meeting->is_open) {
            $meeting->update(['is_open' => false]);
            ActivityLog::record('club_meeting.closed', $meeting);
        }

        return back()->with('success', __('The meeting is finished, check-in is closed.'));
    }

    /** Big QR code for the screen / projector. */
    public function screen(Request $request, ClubMeeting $meeting, CheckinQr $qr): View
    {
        $url = $qr->url($meeting, $request);

        return view('admin.club.meetings.screen', [
            'meeting' => $meeting,
            'url' => $url,
            'svg' => $meeting->is_open ? $qr->svg($url) : null,
            'unreachable' => $qr->unreachable($request),
            'localhost' => str_contains(parse_url($url, PHP_URL_HOST) ?? '', 'localhost') || str_starts_with(parse_url($url, PHP_URL_HOST) ?? '', '127.'),
            'rotation' => config('club.qr_rotation_seconds'),
            'wifi' => $wifi = $meeting->wifi(),
            'wifiSvg' => $wifi ? $qr->svg($qr->wifiPayload($wifi), 220) : null,
        ] + $this->liveData($meeting));
    }

    /** Polled by the screen page: fresh QR code + who already checked in. */
    public function qr(Request $request, ClubMeeting $meeting, CheckinQr $qr): JsonResponse
    {
        $url = $meeting->is_open ? $qr->url($meeting, $request) : null;

        return response()->json(['url' => $url, 'svg' => $url ? $qr->svg($url) : null, 'is_open' => $meeting->is_open] + $this->liveData($meeting));
    }

    public function addAttendee(Request $request, ClubMeeting $meeting): RedirectResponse
    {
        if (! $meeting->is_open) {
            return back()->withErrors(['member_ids' => __('The meeting is finished, nobody can be added any more.')]);
        }

        $data = $request->validate(['member_ids' => ['required', 'array'], 'member_ids.*' => ['integer', Rule::exists('club_members', 'id')]]);

        foreach ($data['member_ids'] as $memberId) {
            ClubAttendance::firstOrCreate(
                ['club_meeting_id' => $meeting->id, 'club_member_id' => $memberId],
                ['checked_in_at' => now(), 'method' => 'manual', 'created_by' => $request->user()->id],
            );
        }
        ActivityLog::record('club_attendance.added', $meeting, ['members' => $data['member_ids']]);

        return back()->with('success', __('Attendance saved.'));
    }

    public function removeAttendee(ClubMeeting $meeting, ClubAttendance $attendance): RedirectResponse
    {
        abort_unless($attendance->club_meeting_id === $meeting->id, 404);

        $attendance->delete();
        ActivityLog::record('club_attendance.removed', $meeting, ['member' => $attendance->club_member_id]);

        return back()->with('success', __('Removed from the attendance list.'));
    }

    /** Attendance list of one meeting as Excel. */
    public function export(ClubMeeting $meeting, MemberSpreadsheet $spreadsheet): BinaryFileResponse
    {
        $members = $meeting->members()->withCount('attendances')->orderBy('last_name')->orderBy('first_name')->get();
        $name = 'attendance-'.$meeting->starts_at->format('Y-m-d').'-'.str($meeting->title)->slug().'.xlsx';

        return response()->download($spreadsheet->write($members), $name)->deleteFileAfterSend();
    }

    private function liveData(ClubMeeting $meeting): array
    {
        return [
            'count' => $meeting->attendances()->count(),
            'latest' => $meeting->attendances()->with('member')->latest('checked_in_at')->latest('id')->limit(8)->get()
                ->map(fn (ClubAttendance $a) => ['name' => $a->member->full_name, 'time' => $a->checked_in_at->format('H:i')]),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'wifi_name' => ['nullable', 'string', 'max:32'], // the longest network name Wi-Fi allows
            'wifi_password' => ['nullable', 'string', 'max:63'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
