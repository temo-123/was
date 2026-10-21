<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\VisitorSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'active');

        $subscriptions = VisitorSubscription::query()
            ->with('visitor', 'ticketType', 'timeSlot', 'ageGroup')
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'expired', fn ($q) => $q->whereNull('cancelled_at')->whereDate('ends_on', '<', today()))
            ->when($status === 'cancelled', fn ($q) => $q->whereNotNull('cancelled_at'))
            ->when($request->input('search'), fn ($q, $term) => $q->whereHas('visitor', fn ($v) => $v->search($term)))
            ->orderBy($status === 'active' ? 'ends_on' : 'created_at', $status === 'active' ? 'asc' : 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('admin.subscriptions.index', compact('subscriptions', 'status'));
    }

    public function cancel(VisitorSubscription $subscription): RedirectResponse
    {
        $subscription->update(['cancelled_at' => now()]);
        ActivityLog::record('subscription.cancelled', $subscription);

        return back()->with('success', __('Subscription cancelled.'));
    }
}
