<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\TicketType;
use App\Models\TimeSlot;
use App\Models\VisitorVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $from = $request->date('from') ?? today();
        $to = $request->date('to') ?? today();

        $query = VisitorVisit::query()
            ->where('entered_at', '>=', $from->copy()->startOfDay())
            ->where('entered_at', '<=', $to->copy()->endOfDay())
            ->when($request->integer('time_slot'), fn ($q, $id) => $q->where('time_slot_id', $id))
            ->when($request->integer('ticket_type'), fn ($q, $id) => $q->where('ticket_type_id', $id))
            ->when($request->input('payment'), fn ($q, $method) => $q->where('payment_method', $method))
            ->when($request->input('search'), fn ($q, $term) => $q->whereHas('visitor', fn (Builder $v) => $v->search($term)));

        if ($request->input('export') === 'csv') {
            return $this->csv($query, $from->toDateString(), $to->toDateString());
        }

        $totals = (clone $query)->selectRaw('COUNT(*) as visits, COALESCE(SUM(price), 0) as revenue')->first();
        $byPayment = (clone $query)->selectRaw('payment_method, COUNT(*) as visits, COALESCE(SUM(price), 0) as revenue')
            ->groupBy('payment_method')->get();

        return view('admin.visits.index', [
            'visits' => $query->with('visitor', 'ticketType', 'timeSlot', 'ageGroup', 'creator')->latest('entered_at')->paginate(50)->withQueryString(),
            'totals' => $totals,
            'byPayment' => $byPayment,
            'from' => $from,
            'to' => $to,
            'timeSlots' => TimeSlot::ordered()->get(),
            'ticketTypes' => TicketType::ordered()->get(),
            'paymentMethods' => VisitorVisit::PAYMENT_METHODS,
        ]);
    }

    public function destroy(VisitorVisit $visit): RedirectResponse
    {
        DB::transaction(function () use ($visit) {
            if ($visit->payment_method === 'subscription' && $visit->subscription && $visit->subscription->visits_used > 0) {
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
            fputcsv($out, ['Date', 'Time', 'First name', 'Last name', 'ID number', 'Age', 'Age group', 'Time slot', 'Ticket', 'Payment', 'Price', 'Staff']);

            $query->with('visitor', 'ticketType', 'timeSlot', 'ageGroup', 'creator')->orderBy('entered_at')
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
                            $visit->payment_method,
                            $visit->price,
                            $visit->creator?->name,
                        ]);
                    }
                });

            fclose($out);
        }, "visits_{$from}_{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
