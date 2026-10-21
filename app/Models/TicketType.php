<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'name_ka', 'duration_days', 'visits_limit', 'sort_order', 'is_active'])]
class TicketType extends Model
{
    protected function casts(): array
    {
        return ['duration_days' => 'integer', 'visits_limit' => 'integer', 'is_active' => 'boolean'];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isSubscription(): bool
    {
        return $this->duration_days !== null;
    }
}
