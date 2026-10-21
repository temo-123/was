<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntryRequest;
use App\Http\Resources\VisitResource;
use App\Models\ActivityLog;
use App\Models\VisitorVisit;
use App\Services\EntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class VisitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $visits = VisitorVisit::query()
            ->with('visitor', 'ticketType', 'timeSlot', 'ageGroup', 'creator')
            ->when($request->date('from'), fn ($q, $from) => $q->where('entered_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn ($q, $to) => $q->where('entered_at', '<=', $to->endOfDay()))
            ->when($request->integer('visitor_id'), fn ($q, $id) => $q->where('visitor_id', $id))
            ->latest('entered_at')
            ->paginate(min($request->integer('per_page', 25), 100));

        return VisitResource::collection($visits);
    }

    /** Register an entrance (existing visitor or a new one). */
    public function store(EntryRequest $request, EntryService $entries): JsonResponse
    {
        $visit = $entries->register($request->entryData(), $request->user());

        return (new VisitResource($visit->load('visitor', 'ticketType', 'timeSlot', 'ageGroup', 'subscription')))
            ->additional(['message' => __('Entrance registered for :name.', ['name' => $visit->visitor->full_name])])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(VisitorVisit $visit): JsonResponse
    {
        DB::transaction(function () use ($visit) {
            // Give the entry back to the subscription it was taken from.
            if ($visit->payment_method === 'subscription' && $visit->subscription && $visit->subscription->visits_used > 0) {
                $visit->subscription->decrement('visits_used');
            }
            $visit->delete();
        });
        ActivityLog::record('visit.deleted', $visit);

        return response()->json(['message' => __('Visit cancelled.')]);
    }
}
