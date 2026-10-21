<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisitorRequest;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorCategory;
use App\Services\EntryService;
use App\Services\VisitorLinker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitorController extends Controller
{
    public function index(Request $request): View
    {
        $visitors = Visitor::query()
            ->search($request->string('search'))
            ->when($request->integer('category'), fn ($q, $id) => $q->where('visitor_category_id', $id))
            ->with('category', 'user')
            ->withCount('visits')
            ->withMax('visits', 'entered_at')
            ->when($request->input('sort') === 'recent', fn ($q) => $q->orderByDesc('visits_max_entered_at'), fn ($q) => $q->orderBy('last_name')->orderBy('first_name'))
            ->paginate(25)
            ->withQueryString();

        return view('admin.visitors.index', [
            'visitors' => $visitors,
            'categories' => VisitorCategory::ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.visitors.form', ['visitor' => new Visitor, 'categories' => VisitorCategory::ordered()->get()]);
    }

    public function store(VisitorRequest $request, EntryService $entries): RedirectResponse
    {
        $visitor = $entries->createVisitor($request->validated(), $request->user());

        return redirect()->route('admin.visitors.show', $visitor)->with('success', __('Visitor created.'));
    }

    public function show(Visitor $visitor): View
    {
        $visitor->load('category', 'user', 'userLink.linker', 'creator');

        return view('admin.visitors.show', [
            'visitor' => $visitor,
            'visits' => $visitor->visits()->with('ticketType', 'timeSlot', 'ageGroup', 'creator')->latest('entered_at')->paginate(15),
            'subscriptions' => $visitor->subscriptions()->with('ticketType', 'timeSlot')->latest('ends_on')->get(),
            'stats' => [
                'visits' => $visitor->visits()->count(),
                'spent' => (float) $visitor->visits()->sum('price') + (float) $visitor->subscriptions()->whereNull('cancelled_at')->sum('price'),
                'last_visit' => $visitor->visits()->max('entered_at'),
            ],
        ]);
    }

    public function edit(Visitor $visitor): View
    {
        return view('admin.visitors.form', ['visitor' => $visitor, 'categories' => VisitorCategory::ordered()->get()]);
    }

    public function update(VisitorRequest $request, Visitor $visitor, VisitorLinker $linker): RedirectResponse
    {
        $data = $request->validated();
        $data['terms_accepted'] = $request->boolean('terms_accepted');
        if ($data['terms_accepted'] && ! $visitor->terms_accepted) {
            $data['terms_accepted_at'] = now();
        }

        $visitor->update($data);
        ActivityLog::record('visitor.updated', $visitor, ['changes' => array_keys($visitor->getChanges())]);
        $linker->linkVisitorToMatchingUser($visitor);

        return redirect()->route('admin.visitors.show', $visitor)->with('success', __('Visitor updated.'));
    }

    public function destroy(Visitor $visitor): RedirectResponse
    {
        $visitor->delete();
        ActivityLog::record('visitor.deleted', $visitor);

        return redirect()->route('admin.visitors.index')->with('success', __('Visitor deleted.'));
    }

    public function link(Request $request, Visitor $visitor, VisitorLinker $linker): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'exists:users,email']]);

        $linker->link($visitor, User::where('email', $data['email'])->firstOrFail(), 'manual', $request->user());

        return back()->with('success', __('Visitor linked to the user account.'));
    }

    public function unlink(Visitor $visitor, VisitorLinker $linker): RedirectResponse
    {
        $linker->unlink($visitor);

        return back()->with('success', __('Link removed.'));
    }
}
