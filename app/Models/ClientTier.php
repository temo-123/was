<?php

namespace App\Models;

use App\Models\Concerns\HasLocalName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A client status such as VIP (−50 % on the entry) or Gold (−100 %), given to visitors by the staff. */
#[Fillable(['name', 'name_ka', 'color', 'discount_percent', 'rental_discount_percent', 'sort_order', 'is_active'])]
class ClientTier extends Model
{
    use HasLocalName;

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'rental_discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    /** "−50 %", "free" or "" for a percent. */
    public static function discountLabel(float $percent): string
    {
        return match (true) {
            $percent >= 100 => __('free'),
            $percent > 0 => '−'.rtrim(rtrim(number_format($percent, 2), '0'), '.').' %',
            default => '',
        };
    }
}
