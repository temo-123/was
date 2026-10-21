<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'visitor_id', 'entered_at', 'age_at_visit', 'age_group_id', 'time_slot_id', 'ticket_type_id',
    'visitor_category_id', 'visitor_subscription_id', 'base_price', 'coefficient', 'price',
    'price_overridden', 'payment_method', 'comment', 'created_by',
])]
class VisitorVisit extends Model
{
    use SoftDeletes;

    public const PAYMENT_METHODS = ['cash', 'card', 'transfer', 'subscription', 'free'];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'base_price' => 'decimal:2',
            'coefficient' => 'decimal:3',
            'price' => 'decimal:2',
            'price_overridden' => 'boolean',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class)->withTrashed();
    }

    public function ageGroup(): BelongsTo
    {
        return $this->belongsTo(AgeGroup::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VisitorCategory::class, 'visitor_category_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(VisitorSubscription::class, 'visitor_subscription_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
