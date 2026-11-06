<?php

namespace App\Models;

use App\Models\Concerns\HasLocalName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A gift for every N-th visit of a visitor (20th, 40th, 60th…): free entry, a discount or a free rental item. */
#[Fillable(['name', 'name_ka', 'every_visits', 'type', 'value', 'rental_item_id', 'sort_order', 'is_active'])]
class VisitReward extends Model
{
    use HasLocalName;

    public const TYPES = [
        'free_entry' => 'Free entry',
        'discount' => 'Discount on the entry',
        'free_rental' => 'Free rental item',
    ];

    protected function casts(): array
    {
        return ['every_visits' => 'integer', 'value' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('every_visits');
    }

    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class);
    }

    /** Whether the visitor's visit with this number gets the reward. */
    public function appliesTo(int $visitNumber): bool
    {
        return $this->every_visits > 0 && $visitNumber > 0 && $visitNumber % $this->every_visits === 0;
    }

    /** "Free entry", "−30 % on the entry", "Free: Climbing shoes". */
    public function describe(): string
    {
        return match ($this->type) {
            'free_entry' => __('Free entry'),
            'discount' => __(':percent off the entry', ['percent' => ClientTier::discountLabel((float) $this->value)]),
            'free_rental' => __('Free: :item', ['item' => $this->rentalItem?->local_name ?? '—']),
            default => $this->type,
        };
    }
}
