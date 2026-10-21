<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'visitor_id', 'ticket_type_id', 'time_slot_id', 'age_group_id', 'visitor_category_id',
    'starts_on', 'ends_on', 'visits_limit', 'visits_used', 'base_price', 'coefficient', 'price',
    'payment_method', 'cancelled_at', 'created_by',
])]
class VisitorSubscription extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'cancelled_at' => 'datetime',
            'base_price' => 'decimal:2',
            'coefficient' => 'decimal:3',
            'price' => 'decimal:2',
        ];
    }

    /** Not cancelled, inside its date range, and with entries left. */
    public function scopeActive(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $date = ($at ?? now())->toDateString();

        return $query->whereNull('cancelled_at')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->where(fn (Builder $q) => $q->whereNull('visits_limit')->orWhereColumn('visits_used', '<', 'visits_limit'));
    }

    public function isActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->cancelled_at === null
            && $this->starts_on->startOfDay()->lte($at)
            && $this->ends_on->endOfDay()->gte($at)
            && ($this->visits_limit === null || $this->visits_used < $this->visits_limit);
    }

    /**
     * A subscription bought for a time slot is valid in that slot and in every
     * earlier (cheaper) slot, e.g. an evening pass also works in the morning.
     * Change this method if the gym's rules differ.
     */
    public function coversTimeSlot(TimeSlot $slot): bool
    {
        $own = $this->timeSlot;

        return $own && ((int) $own->id === (int) $slot->id || $slot->sort_order <= $own->sort_order);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class)->withTrashed();
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function ageGroup(): BelongsTo
    {
        return $this->belongsTo(AgeGroup::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VisitorCategory::class, 'visitor_category_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(VisitorVisit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
