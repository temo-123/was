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
    public function index(): View
    {
        return view('admin.club.meetings.index', [
            'meetings' => ClubMeeting::withCount('attendances')->latest('starts_at')->paginate(25),
            'activeMembers' => ClubMember::where('is_active', true)->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.club.meetings.form', ['meeting' => new ClubMeeting(['starts_at' => now()->startOfHour(), 'is_open' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $meeting = ClubMeeting::create($this->validated($request) + ['created_by' => $request->user()->id]);
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

    /** Open / close check-in. */
    public function toggle(ClubMeeting $meeting): RedirectResponse
    {
        $meeting->update(['is_open' => ! $meeting->is_open]);
        ActivityLog::record($meeting->is_open ? 'club_meeting.opened' : 'club_meeting.closed', $meeting);

        return back()->with('success', $meeting->is_open ? __('Check-in is open.') : __('Check-in is closed.'));
    }

    /** Big QR code for the screen / projector. */
    public function screen(Request $request, ClubMeeting $meeting, CheckinQr $qr): View
    {
        $url = $qr->url($meeting, $request);

        return view('admin.club.meetings.screen', [
            'meeting' => $meeting,
            'url' => $url,
            'svg' => $qr->svg($url),
            'unreachable' => $qr->unreachable($request),
            'localhost' => str_contains(parse_url($url, PHP_URL_HOST) ?? '', 'localhost') || str_starts_with(parse_url($url, PHP_URL_HOST) ?? '', '127.'),
            'rotation' => config('club.qr_rotation_seconds'),
        ] + $this->liveData($meeting));
    }

    /** Polled by the screen page: fresh QR code + who already checked in. */
    public function qr(Request $request, ClubMeeting $meeting, CheckinQr $qr): JsonResponse
    {
        $url = $qr->url($meeting, $request);

        return response()->json(['url' => $url, 'svg' => $qr->svg($url), 'is_open' => $meeting->is_open] + $this->liveData($meeting));
    }

    public function addAttendee(Request $request, ClubMeeting $meeting): RedirectResponse
    {
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
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $data + ['is_open' => $request->boolean('is_open')];
    }
}
