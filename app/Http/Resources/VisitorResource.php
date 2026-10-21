<?php

namespace App\Http\Resources;

use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Visitor */
class VisitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'personal_id' => $this->personal_id,
            'birth_date' => $this->birth_date?->toDateString(),
            'age' => $this->birth_date ? $this->ageAt() : null,
            'phone' => $this->phone,
            'email' => $this->email,
            'comment' => $this->comment,
            'visitor_category_id' => $this->visitor_category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->only(['id', 'code', 'name', 'name_ka'])),
            'terms_accepted' => $this->terms_accepted,
            'terms_accepted_at' => $this->terms_accepted_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user?->only(['id', 'name', 'email'])),
            'visits_count' => $this->whenCounted('visits'),
            'last_visit_at' => $this->when(isset($this->visits_max_entered_at), fn () => $this->visits_max_entered_at),
            'active_subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
