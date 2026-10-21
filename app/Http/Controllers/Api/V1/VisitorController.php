<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisitorRequest;
use App\Http\Resources\VisitorResource;
use App\Http\Resources\VisitResource;
use App\Models\ActivityLog;
use App\Models\Visitor;
use App\Services\EntryService;
use App\Services\VisitorLinker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VisitorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $visitors = Visitor::query()
            ->search($request->string('search'))
            ->with('category', 'user')
            ->withCount('visits')
            ->withMax('visits', 'entered_at')
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(min($request->integer('per_page', 25), 100));

        return VisitorResource::collection($visitors);
    }

    /** Fast autocomplete for the entrance form. */
    public function search(Request $request): AnonymousResourceCollection
    {
        $term = trim($request->string('q'));

        $visitors = mb_strlen($term) < 2 ? collect() : Visitor::query()
            ->search($term)
            ->with(['category', 'subscriptions' => fn ($q) => $q->active()->with('ticketType', 'timeSlot')])
            ->withCount('visits')
            ->withMax('visits', 'entered_at')
            ->orderBy('last_name')
            ->limit(10)
            ->get();

        return VisitorResource::collection($visitors);
    }

    public function store(VisitorRequest $request, EntryService $entries): JsonResponse
    {
        $visitor = $entries->createVisitor($request->validated(), $request->user());

        return (new VisitorResource($visitor->load('category', 'user')))->response()->setStatusCode(201);
    }

    public function show(Visitor $visitor): VisitorResource
    {
        return new VisitorResource($visitor->load(['category', 'user', 'subscriptions' => fn ($q) => $q->active()->with('ticketType', 'timeSlot')])->loadCount('visits'));
    }

    public function update(VisitorRequest $request, Visitor $visitor, VisitorLinker $linker): VisitorResource
    {
        $data = $request->validated();
        if (! empty($data['terms_accepted']) && ! $visitor->terms_accepted) {
            $data['terms_accepted_at'] = now();
        }
        $visitor->update($data);
        ActivityLog::record('visitor.updated', $visitor, ['changes' => array_keys($visitor->getChanges())]);
        $linker->linkVisitorToMatchingUser($visitor);

        return new VisitorResource($visitor->load('category', 'user'));
    }

    public function destroy(Visitor $visitor): JsonResponse
    {
        $visitor->delete();
        ActivityLog::record('visitor.deleted', $visitor);

        return response()->json(['message' => __('Visitor deleted.')]);
    }

    public function visits(Visitor $visitor): AnonymousResourceCollection
    {
        return VisitResource::collection(
            $visitor->visits()->with('ticketType', 'timeSlot', 'ageGroup', 'creator')->latest('entered_at')->paginate(25)
        );
    }
}
