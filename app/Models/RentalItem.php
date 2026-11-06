<?php

namespace App\Models;

use App\Models\Concerns\HasLocalName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Gear the gym rents out with an entrance: climbing shoes (with a size, returned), chalk (not returned)… */
#[Fillable(['name', 'name_ka', 'price', 'has_sizes', 'is_returnable', 'stock', 'sort_order', 'is_active'])]
class RentalItem extends Model
{
    use HasLocalName;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'has_sizes' => 'boolean',
            'is_returnable' => 'boolean',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(VisitRental::class);
    }

    /** Pieces given out and not returned yet (only for gear that comes back). */
    public function outCount(): int
    {
        return $this->is_returnable ? (int) $this->rentals()->out()->sum('quantity') : 0;
    }
}
