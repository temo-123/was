<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\RentalItem;
use App\Models\VisitorVisit;
use App\Models\VisitRental;
use App\Services\Pricing\Checkout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Rental gear: the list of items with prices, what is out right now, giving gear to someone already in the gym. */
class RentalController extends Controller
{
    public function index(): View
    {
        $items = RentalItem::ordered()->get();
        $out = VisitRental::out()->with('visit.visitor', 'creator')->latest()->get();

        return view('admin.rentals.index', [
            'items' => $items,
            'out' => $out,
            'outByItem' => $out->groupBy('rental_item_id')->map->sum('quantity'),
            'todayVisits' => VisitorVisit::whereDate('entered_at', today())->with('visitor')->latest('entered_at')->get(),
            'rentedToday' => VisitRental::whereHas('visit', fn ($q) => $q->whereDate('entered_at', today()))
                ->selectRaw('COALESCE(SUM(quantity), 0) as pieces, COALESCE(SUM(price), 0) as money')->first(),
            'paymentMethods' => ['cash', 'card', 'transfer'],
        ]);
    }

    public function saveItem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:rental_items,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_ka' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:10000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);
        $data += [
            'has_sizes' => $request->boolean('has_sizes'),
            'is_returnable' => $request->boolean('is_returnable'),
            'is_active' => $request->boolean('is_active'),
        ];
        $data['sort_order'] ??= 0;

        $item = RentalItem::updateOrCreate(['id' => $data['id'] ?? null], collect($data)->except('id')->all());
        ActivityLog::record($item->wasRecentlyCreated ? 'rental_item.created' : 'rental_item.updated', $item, ['price' => $item->price]);

        return back()->with('success', __(':name saved.', ['name' => $item->local_name]));
    }

    /** Somebody already in the gym wants gear: added to their visit of today and its price. */
    public function addToVisit(Request $request, Checkout $checkout): RedirectResponse
    {
        $data = $request->validate([
            'visitor_visit_id' => ['required', Rule::exists('visitor_visits', 'id')->whereNull('deleted_at')],
            'rental_item_id' => ['required', Rule::exists('rental_items', 'id')->where('is_active', true)],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'size' => ['nullable', 'string', 'max:16'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'transfer'])],
        ]);
        $visit = VisitorVisit::with('tier', 'visitor')->findOrFail($data['visitor_visit_id']);
        abort_unless($visit->entered_at->isToday(), 422, __('Gear can be added only to a visit of today.'));

        $line = $checkout->rentalLine(RentalItem::findOrFail($data['rental_item_id']), $data['quantity'] ?? 1, $data['size'] ?? null, $visit->tier);

        DB::transaction(function () use ($visit, $line, $data, $request) {
            $visit->rentals()->create($line + ['created_by' => $request->user()->id]);
            $visit->rental_price += $line['price'];
            $visit->total_price += $line['price'];
            if ($line['price'] > 0 && in_array($visit->payment_method, ['free', 'subscription'], true)) {
                $visit->payment_method = $data['payment_method'] ?? 'cash';
            }
            $visit->save();
        });
        ActivityLog::record('rental.added', $visit, ['item' => $line['name'], 'price' => $line['price']]);

        return back()->with('success', __(':item for :name: :price ₾.', ['item' => $line['name'], 'name' => $visit->visitor->full_name, 'price' => number_format($line['price'], 2)]));
    }

    public function markReturned(Request $request, VisitRental $rental): RedirectResponse
    {
        if (! $rental->returned_at) {
            $rental->update(['returned_at' => now(), 'returned_by' => $request->user()->id]);
            ActivityLog::record('rental.returned', $rental->visit, ['item' => $rental->name]);
        }

        return back()->with('success', __('Returned: :item.', ['item' => $rental->local_name]));
    }
}
