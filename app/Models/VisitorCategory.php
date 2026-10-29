<?php

namespace App\Models;

use App\Models\Concerns\HasLocalName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'name_ka', 'is_default', 'sort_order', 'is_active'])]
class VisitorCategory extends Model
{
    use HasLocalName;

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::ordered()->first();
    }
}
