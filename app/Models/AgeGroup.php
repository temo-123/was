<?php

namespace App\Models;

use App\Models\Concerns\HasLocalName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'name_ka', 'min_age', 'max_age', 'sort_order', 'is_active'])]
class AgeGroup extends Model
{
    use HasLocalName;

    protected function casts(): array
    {
        return ['min_age' => 'integer', 'max_age' => 'integer', 'is_active' => 'boolean'];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('min_age');
    }

    public static function forAge(int $age): ?self
    {
        return static::where('is_active', true)
            ->where('min_age', '<=', $age)
            ->where(fn (Builder $q) => $q->whereNull('max_age')->orWhere('max_age', '>', $age))
            ->orderByDesc('min_age')
            ->first();
    }

    public function label(): string
    {
        return $this->max_age === null ? "{$this->min_age}+" : "{$this->min_age}–{$this->max_age}";
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }
}
