<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\AgeGroup;
use App\Models\PriceRule;
use App\Models\TicketType;
use App\Models\TimeSlot;
use App\Models\Visitor;
use App\Models\VisitorCategory;
use App\Services\EntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PricingController extends Controller
{
    /** Public price list (for the website / mobile app). */
    public function index(): JsonResponse
    {
        return response()->json([
            'age_groups' => AgeGroup::where('is_active', true)->ordered()->get()->map(fn ($g) => $g->only(['id', 'code', 'name', 'name_ka', 'min_age', 'max_age']) + ['label' => $g->label()]),
            'time_slots' => TimeSlot::where('is_active', true)->ordered()->get()->map(fn ($s) => $s->only(['id', 'code', 'name', 'name_ka', 'start_time', 'end_time']) + ['label' => $s->label()]),
            'ticket_types' => TicketType::where('is_active', true)->ordered()->get(['id', 'code', 'name', 'name_ka', 'duration_days', 'visits_limit']),
            'categories' => VisitorCategory::where('is_active', true)->ordered()->get(['id', 'code', 'name', 'name_ka', 'is_default']),
            'prices' => PriceRule::where('is_active', true)->get(['age_group_id', 'time_slot_id', 'ticket_type_id', 'visitor_category_id', 'price']),
        ]);
    }

    /** Price for a visitor (or a birth date for a new visitor) at a given moment (default: now). */
    public function quote(Request $request, EntryService $entries): JsonResponse
    {
        $data = $request->validate([
            'visitor_id' => ['nullable', 'required_without:birth_date', 'exists:visitors,id'],
            'birth_date' => ['nullable', 'required_without:visitor_id', 'date', 'before:today'],
            'visitor_category_id' => ['nullable', 'exists:visitor_categories,id'],
            'ticket_type_id' => ['required', 'exists:ticket_types,id'],
            'at' => ['nullable', 'date'],
        ]);

        $at = isset($data['at']) ? Carbon::parse($data['at']) : now();
        $visitor = isset($data['visitor_id']) ? Visitor::find($data['visitor_id']) : null;
        $age = $visitor ? $visitor->ageAt($at) : (int) Carbon::parse($data['birth_date'])->diffInYears($at);
        $categoryId = $data['visitor_category_id'] ?? $visitor?->visitor_category_id ?? VisitorCategory::default()?->id;

        $result = $entries->preview($visitor, $age, $categoryId, TicketType::findOrFail($data['ticket_type_id']), $at);

        return response()->json([
            'quote' => $result['quote']->toArray(),
            'subscription' => $result['subscription'] ? new SubscriptionResource($result['subscription']) : null,
            'amount' => $result['amount'],
            'last_visit_at' => $visitor?->visits()->max('entered_at'),
        ]);
    }
}
