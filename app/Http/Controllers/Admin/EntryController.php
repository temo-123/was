<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketType;
use App\Models\VisitorCategory;
use App\Models\VisitorVisit;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The visitor entrance screen (a Vue component talking to /api/v1). */
class EntryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('admin.entry', [
            'config' => [
                'ticketTypes' => TicketType::where('is_active', true)->ordered()->get(['id', 'code', 'name', 'name_ka', 'duration_days']),
                'categories' => VisitorCategory::where('is_active', true)->ordered()->get(['id', 'code', 'name', 'name_ka', 'is_default']),
                'paymentMethods' => array_values(array_diff(VisitorVisit::PAYMENT_METHODS, ['subscription', 'free'])),
                'can' => [
                    'createVisitor' => $user->can('visitors.create'),
                    'overridePrice' => $user->can('visits.override_price'),
                ],
                'urls' => [
                    'search' => route('api.v1.visitors.search'),
                    'quote' => route('api.v1.pricing.quote'),
                    'store' => route('api.v1.visits.store'),
                    'storeVisitor' => route('api.v1.visitors.store'),
                    'visitor' => route('admin.visitors.show', ['visitor' => '__ID__']),
                    'visitorApi' => route('api.v1.visitors.show', ['visitor' => '__ID__']),
                    'terms' => route('terms'),
                ],
                'preselectVisitorId' => $request->integer('visitor') ?: null,
            ],
        ]);
    }
}
