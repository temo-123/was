<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeSlot;
use App\Models\Visitor;
use App\Models\VisitorSubscription;
use App\Models\VisitorVisit;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today();

        $visitsToday = VisitorVisit::whereDate('entered_at', $today);
        $subscriptionsSoldToday = VisitorSubscription::whereNull('cancelled_at')->whereDate('created_at', $today);

        $last7 = VisitorVisit::where('entered_at', '>=', $today->copy()->subDays(6))
            ->selectRaw('DATE(entered_at) as day, COUNT(*) as visits, SUM(price) as revenue')
            ->groupBy('day')
            ->pluck('visits', 'day');

        $days = collect(range(6, 0))->map(function (int $ago) use ($today, $last7) {
            $date = $today->copy()->subDays($ago);

            return ['date' => $date, 'visits' => (int) ($last7[$date->toDateString()] ?? 0)];
        });

        return view('admin.dashboard', [
            'stats' => [
                'visits_today' => (clone $visitsToday)->count(),
                'revenue_today' => (float) (clone $visitsToday)->sum('price') + (float) (clone $subscriptionsSoldToday)->sum('price'),
                'subscriptions_sold_today' => (clone $subscriptionsSoldToday)->count(),
                'active_subscriptions' => VisitorSubscription::active()->count(),
                'visitors_total' => Visitor::count(),
                'new_visitors_today' => Visitor::whereDate('created_at', $today)->count(),
            ],
            'days' => $days,
            'maxDay' => max(1, $days->max('visits')),
            'bySlot' => TimeSlot::ordered()->withCount(['visits' => fn ($q) => $q->whereDate('entered_at', $today)])->get(),
            'latestVisits' => (clone $visitsToday)->with('visitor', 'ticketType', 'timeSlot')->latest('entered_at')->limit(10)->get(),
            'expiringSoon' => VisitorSubscription::active()->with('visitor', 'ticketType')
                ->whereDate('ends_on', '<=', Carbon::today()->addDays(3))->orderBy('ends_on')->limit(10)->get(),
            'currentSlot' => TimeSlot::forTime(now()),
        ]);
    }
}
