<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rental item given with a visit: a snapshot of its name and price, and when it came back. */
#[Fillable([
    'visitor_visit_id', 'rental_item_id', 'name', 'name_ka', 'quantity', 'size', 'unit_price', 'discount_amount', 'price',
    'is_reward', 'is_returnable', 'returned_at', 'returned_by', 'created_by',
])]
class VisitRental extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'price' => 'decimal:2',
            'is_reward' => 'boolean',
            'is_returnable' => 'boolean',
            'returned_at' => 'datetime',
        ];
    }

    protected function localName(): Attribute
    {
        return Attribute::get(fn () => app()->getLocale() === 'ka' && filled($this->name_ka) ? $this->name_ka : $this->name);
    }

    /** Gear that should come back and hasn't yet (of visits that weren't cancelled). */
    public function scopeOut(Builder $query): Builder
    {
        return $query->where('is_returnable', true)->whereNull('returned_at')->whereHas('visit');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(VisitorVisit::class, 'visitor_visit_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class, 'rental_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
