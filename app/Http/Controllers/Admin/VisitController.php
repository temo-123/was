<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\TicketType;
use App\Models\TimeSlot;
use App\Models\VisitorVisit;
use App\Services\VisitCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitController extends Controller
{
    public function index(Request $request, VisitCalendar $calendar): View|StreamedResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        if ($request->query('view') === 'calendar') {
            return $this->calendar($request, $calendar);
        }

        $from = $request->date('from') ?? today();
        $to = $request->date('to') ?? today();

        $query = $this->filtered($request)
            ->where('entered_at', '>=', $from->copy()->startOfDay())
            ->where('entered_at', '<=', $to->copy()->endOfDay());

        if ($request->input('export') === 'csv') {
            return $this->csv($query, $from->toDateString(), $to->toDateString());
        }

        $totals = (clone $query)->selectRaw('COUNT(*) as visits, COALESCE(SUM(total_price), 0) as revenue, COALESCE(SUM(rental_price), 0) as rentals')->first();
        $byPayment = (clone $query)->selectRaw('payment_method, COUNT(*) as visits, COALESCE(SUM(total_price), 0) as revenue')
            ->groupBy('payment_method')->get();

        return view('admin.visits.index', $this->filterOptions() + [
            'view' => 'list',
            'visits' => $query->with('visitor', 'ticketType', 'timeSlot', 'ageGroup', 'creator', 'rentals', 'tier')->latest('entered_at')->paginate(50)->withQueryString(),
            'totals' => $totals,
            'byPayment' => $byPayment,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** All visits of a month on a calendar: visits and money per day; a day opens the list of that day. */
    private function calendar(Request $request, VisitCalendar $calendar): View
    {
        $month = $calendar->month($request);
        $from = $month->copy()->startOfMonth()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        $to = $month->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SUNDAY)->endOfDay();

        $days = $this->filtered($request)
            ->whereBetween('entered_at', [$from, $to])
            ->selectRaw('DATE(entered_at) as day, COUNT(*) as visits, COALESCE(SUM(total_price), 0) as revenue')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => \Illuminate\Support\Carbon::parse($row->day)->toDateString());
        $inMonth = $days->filter(fn ($row, $day) => str_starts_with($day, $month->format('Y-m')));

        return view('admin.visits.index', $this->filterOptions() + [
            'view' => 'calendar',
            'month' => $month,
            'days' => $days,
            'maxDay' => (int) $inMonth->max('visits'),
            'totals' => (object) ['visits' => (int) $inMonth->sum('visits'), 'revenue' => (float) $inMonth->sum('revenue'), 'rentals' => 0],
            'byPayment' => collect(),
            'from' => $month->copy()->startOfMonth(),
            'to' => $month->copy()->endOfMonth(),
        ]);
    }

    /** The list filters (hours, ticket, payment, visitor) without the dates. */
    private function filtered(Request $request): Builder
    {
        return VisitorVisit::query()
            ->when($request->integer('time_slot'), fn ($q, $id) => $q->where('time_slot_id', $id))
            ->when($request->integer('ticket_type'), fn ($q, $id) => $q->where('ticket_type_id', $id))
            ->when($request->input('payment'), fn ($q, $method) => $q->where('payment_method', $method))
            ->when($request->input('search'), fn ($q, $term) => $q->whereHas('visitor', fn (Builder $v) => $v->search($term)));
    }

    private function filterOptions(): array
    {
        return [
            'timeSlots' => TimeSlot::ordered()->get(),
            'ticketTypes' => TicketType::ordered()->get(),
            'paymentMethods' => VisitorVisit::PAYMENT_METHODS,
        ];
    }

    public function destroy(VisitorVisit $visit): RedirectResponse
    {
        DB::transaction(function () use ($visit) {
            if ($visit->visitor_subscription_id && $visit->subscription && $visit->subscription->visits_used > 0) {
                $visit->subscription->decrement('visits_used');
            }
            $visit->delete();
        });
        ActivityLog::record('visit.deleted', $visit);

        return back()->with('success', __('Visit cancelled.'));
    }

    protected function csv(Builder $query, string $from, string $to): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Georgian letters correctly
            fputcsv($out, ['Date', 'Time', 'First name', 'Last name', 'ID number', 'Age', 'Age group', 'Time slot', 'Ticket', 'Visit no.', 'Client status', 'Discount', 'Rewards', 'Entry price', 'Rental gear', 'Gear price', 'Total', 'Payment', 'Staff']);

            $query->with('visitor', 'ticketType', 'timeSlot', 'ageGroup', 'creator', 'rentals', 'tier')->orderBy('entered_at')
                ->chunk(500, function ($visits) use ($out) {
                    foreach ($visits as $visit) {
                        fputcsv($out, [
                            $visit->entered_at->format('Y-m-d'),
                            $visit->entered_at->format('H:i'),
                            $visit->visitor?->first_name,
                            $visit->visitor?->last_name,
                            $visit->visitor?->personal_id,
                            $visit->age_at_visit,
                            $visit->ageGroup?->name,
                            $visit->timeSlot?->name,
                            $visit->ticketType?->name,
                            $visit->visit_number,
                            $visit->tier?->name,
                            $visit->discount_amount,
                            collect($visit->rewards)->pluck('name')->implode(', '),
                            $visit->price,
                            $visit->rentals->map(fn ($r) => $r->name.($r->size ? " {$r->size}" : '').($r->quantity > 1 ? " ×{$r->quantity}" : ''))->implode(', '),
                            $visit->rental_price,
                            $visit->total_price,
                            $visit->payment_method,
                            $visit->creator?->name,
                        ]);
                    }
                });

            fclose($out);
        }, "visits_{$from}_{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
