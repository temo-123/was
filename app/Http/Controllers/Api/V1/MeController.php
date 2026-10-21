<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\VisitResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Data of the logged-in climber (their linked visitor card). */
class MeController extends Controller
{
    public function visits(Request $request): AnonymousResourceCollection
    {
        $visitor = $request->user()->visitor;

        return VisitResource::collection(
            $visitor ? $visitor->visits()->with('ticketType', 'timeSlot')->latest('entered_at')->paginate(25) : collect()
        );
    }

    public function subscriptions(Request $request): AnonymousResourceCollection
    {
        $visitor = $request->user()->visitor;

        return SubscriptionResource::collection(
            $visitor ? $visitor->subscriptions()->with('ticketType', 'timeSlot')->latest('ends_on')->get() : collect()
        );
    }
}
