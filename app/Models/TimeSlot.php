<?php

namespace App\Models;

use App\Models\Concerns\HasLocalName;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'name_ka', 'start_time', 'end_time', 'sort_order', 'is_active'])]
class TimeSlot extends Model
{
    use HasLocalName;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('start_time');
    }

    public static function forTime(CarbonInterface $at): ?self
    {
        $time = $at->format('H:i:s');

        return static::where('is_active', true)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->ordered()
            ->first();
    }

    public function label(): string
    {
        return substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(VisitorVisit::class);
    }
}
