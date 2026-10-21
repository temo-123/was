<?php

namespace App\Http\Resources;

use App\Models\VisitorSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VisitorSubscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visitor_id' => $this->visitor_id,
            'visitor' => $this->whenLoaded('visitor', fn () => ['id' => $this->visitor->id, 'full_name' => $this->visitor->full_name]),
            'ticket_type' => $this->whenLoaded('ticketType', fn () => $this->ticketType->only(['id', 'code', 'name', 'name_ka'])),
            'time_slot' => $this->whenLoaded('timeSlot', fn () => $this->timeSlot->only(['id', 'code', 'name', 'name_ka']) + ['label' => $this->timeSlot->label()]),
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'visits_limit' => $this->visits_limit,
            'visits_used' => $this->visits_used,
            'price' => (float) $this->price,
            'payment_method' => $this->payment_method,
            'is_active' => $this->isActive(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
