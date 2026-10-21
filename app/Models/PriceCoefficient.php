<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name', 'description', 'coefficient', 'age_group_id', 'time_slot_id', 'ticket_type_id',
    'visitor_category_id', 'weekdays', 'date_from', 'date_to', 'is_active',
])]
class PriceCoefficient extends Model
{
    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:3',
            'weekdays' => 'array',
            'date_from' => 'date',
            'date_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** Whether this coefficient applies to the given price context. Null conditions match anything. */
    public function appliesTo(int $ageGroupId, int $timeSlotId, int $ticketTypeId, ?int $categoryId, CarbonInterface $at): bool
    {
        return $this->is_active
            && ($this->age_group_id === null || (int) $this->age_group_id === (int) $ageGroupId)
            && ($this->time_slot_id === null || (int) $this->time_slot_id === (int) $timeSlotId)
            && ($this->ticket_type_id === null || (int) $this->ticket_type_id === (int) $ticketTypeId)
            && ($this->visitor_category_id === null || (int) $this->visitor_category_id === (int) $categoryId)
            && (empty($this->weekdays) || in_array($at->isoWeekday(), array_map('intval', $this->weekdays), true))
            && ($this->date_from === null || $this->date_from->startOfDay()->lte($at))
            && ($this->date_to === null || $this->date_to->endOfDay()->gte($at));
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
}
