<?php

namespace App\Http\Resources;

use App\Models\VisitorVisit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VisitorVisit */
class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visitor_id' => $this->visitor_id,
            'visitor' => $this->whenLoaded('visitor', fn () => [
                'id' => $this->visitor->id,
                'full_name' => $this->visitor->full_name,
                'personal_id' => $this->visitor->personal_id,
            ]),
            'entered_at' => $this->entered_at->toIso8601String(),
            'age_at_visit' => $this->age_at_visit,
            'age_group' => $this->whenLoaded('ageGroup', fn () => $this->ageGroup?->only(['id', 'code', 'name'])),
            'time_slot' => $this->whenLoaded('timeSlot', fn () => $this->timeSlot?->only(['id', 'code', 'name'])),
            'ticket_type' => $this->whenLoaded('ticketType', fn () => $this->ticketType?->only(['id', 'code', 'name'])),
            'subscription_id' => $this->visitor_subscription_id,
            'base_price' => (float) $this->base_price,
            'coefficient' => (float) $this->coefficient,
            'price' => (float) $this->price,
            'price_overridden' => $this->price_overridden,
            'payment_method' => $this->payment_method,
            'comment' => $this->comment,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
        ];
    }
}
