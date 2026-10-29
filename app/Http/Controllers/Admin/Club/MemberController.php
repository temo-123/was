<?php

namespace App\Http\Controllers\Admin\Club;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClubMemberRequest;
use App\Models\ActivityLog;
use App\Models\ClubMember;
use App\Services\Club\MemberSpreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $members = $this->filtered($request)
            ->withCount('attendances')
            ->withMax('attendances', 'checked_in_at')
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.club.members.index', [
            'members' => $members,
            'pendingTab' => $request->input('tab') === 'new',
            'counts' => ClubMember::selectRaw('is_pending, COUNT(*) as total')->groupBy('is_pending')->pluck('total', 'is_pending'),
            'suggestions' => $request->input('tab') === 'new' ? $members->getCollection()->mapWithKeys(fn (ClubMember $m) => [$m->id => $this->similar($m)->first()]) : collect(),
        ]);
    }

    public function create(): View
    {
        return view('admin.club.members.form', ['member' => new ClubMember(['is_active' => true])]);
    }

    public function store(ClubMemberRequest $request): RedirectResponse
    {
        $member = ClubMember::create($request->validated() + [
            'is_active' => $request->boolean('is_active'),
            'created_by' => $request->user()->id,
        ]);
        ActivityLog::record('club_member.created', $member);

        return redirect()->route('admin.club.members.index')->with('success', __('Club member :name added.', ['name' => $member->full_name]));
    }

    public function edit(ClubMember $member): View
    {
        return view('admin.club.members.form', [
            'member' => $member,
            'attendances' => $member->attendances()->with('meeting')->latest('checked_in_at')->limit(50)->get(),
            'similar' => $member->is_pending ? $this->similar($member)->get() : collect(),
            'mergeTargets' => $member->is_pending
                ? ClubMember::where('is_pending', false)->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'phone'])
                : collect(),
        ]);
    }

    public function update(ClubMemberRequest $request, ClubMember $member): RedirectResponse
    {
        $member->update($request->validated() + ['is_active' => $request->boolean('is_active')]);
        ActivityLog::record('club_member.updated', $member, ['changes' => array_keys($member->getChanges())]);

        return redirect()->route('admin.club.members.index', $member->is_pending ? ['tab' => 'new'] : [])->with('success', __('Club member updated.'));
    }

    public function destroy(ClubMember $member): RedirectResponse
    {
        $member->delete();
        ActivityLog::record('club_member.deleted', $member, ['name' => $member->full_name]);

        return redirect()->route('admin.club.members.index', $member->is_pending ? ['tab' => 'new'] : [])->with('success', __('Club member deleted.'));
    }

    /** Moves a self-registered member to the main list. */
    public function approve(ClubMember $member): RedirectResponse
    {
        $member->update(['is_pending' => false]);
        ActivityLog::record('club_member.approved', $member);

        return back()->with('success', __(':name was added to the member list.', ['name' => $member->full_name]));
    }

    /** A self-registered member who already exists: moves their attendance to the existing member and removes the copy. */
    public function merge(Request $request, ClubMember $member): RedirectResponse
    {
        abort_unless($member->is_pending, 404);
        $data = $request->validate(['target_id' => ['required', 'integer', Rule::exists('club_members', 'id')->where('is_pending', false)]]);
        $target = ClubMember::findOrFail($data['target_id']);

        DB::transaction(function () use ($member, $target) {
            $member->attendances()
                ->whereNotIn('club_meeting_id', $target->attendances()->pluck('club_meeting_id'))
                ->update(['club_member_id' => $target->id]);
            $member->delete(); // attendance at meetings the target already has goes with it
            // Contacts are unique, so they can move only after the copy is gone.
            foreach (['personal_id', 'phone', 'email'] as $field) {
                $target->{$field} ??= $member->{$field};
            }
            $target->save();
        });
        ActivityLog::record('club_member.merged', $target, ['from' => $member->id, 'name' => $member->full_name]);

        return redirect()->route('admin.club.members.index', ['tab' => 'new'])
            ->with('success', __(':from was merged into :to.', ['from' => $member->full_name, 'to' => $target->full_name]));
    }

    /** Excel file with the members matching the current filter. */
    public function export(Request $request, MemberSpreadsheet $spreadsheet): BinaryFileResponse
    {
        $members = $this->filtered($request)->withCount('attendances')->orderBy('last_name')->orderBy('first_name')->lazy();
        ActivityLog::record('club_member.exported');

        return response()->download($spreadsheet->write($members), 'club-members-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }

    /** Empty Excel file with the column names the importer understands. */
    public function template(MemberSpreadsheet $spreadsheet): BinaryFileResponse
    {
        return response()->download($spreadsheet->write([], false), 'club-members-template.xlsx')->deleteFileAfterSend();
    }

    /** Main-list members that look like this self-registered one (same name or phone). */
    private function similar(ClubMember $member): Builder
    {
        return ClubMember::where('is_pending', false)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w->where('first_name', $member->first_name)->where('last_name', $member->last_name))
                ->orWhere(fn (Builder $w) => $w->where('first_name', $member->last_name)->where('last_name', $member->first_name))
                ->when($member->phone, fn (Builder $w) => $w->orWhere('phone', $member->phone)))
            ->orderBy('last_name');
    }

    private function filtered(Request $request): Builder
    {
        return ClubMember::query()
            ->where('is_pending', $request->input('tab') === 'new')
            ->search($request->string('search'))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false));
    }
}
