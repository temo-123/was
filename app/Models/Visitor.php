<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'first_name', 'last_name', 'personal_id', 'birth_date', 'phone', 'email', 'comment',
    'visitor_category_id', 'client_tier_id', 'client_tier_until', 'terms_accepted', 'terms_accepted_at', 'created_by',
])]
class Visitor extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'client_tier_until' => 'date',
            'terms_accepted' => 'boolean',
            'terms_accepted_at' => 'datetime',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /** Full years at the given moment (today by default). */
    public function ageAt(?CarbonInterface $at = null): int
    {
        return (int) $this->birth_date->diffInYears($at ?? now());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VisitorCategory::class, 'visitor_category_id');
    }

    /** Client status (VIP, Gold…); see activeTier() for whether it still counts. */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(ClientTier::class, 'client_tier_id');
    }

    /** The client status that gives a discount on the given day: set, switched on and not expired. */
    public function activeTier(?CarbonInterface $at = null): ?ClientTier
    {
        $tier = $this->tier;
        if (! $tier || ! $tier->is_active) {
            return null;
        }
        if ($this->client_tier_until && $this->client_tier_until->endOfDay()->lt($at ?? now())) {
            return null;
        }

        return $tier;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(VisitorVisit::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(VisitorSubscription::class);
    }

    public function userLink(): HasOne
    {
        return $this->hasOne(VisitorUser::class);
    }

    /** The registered user account linked to this visitor, if any. */
    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, VisitorUser::class, 'visitor_id', 'id', 'id', 'user_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            foreach (preg_split('/\s+/', $term) as $word) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';
                $q->where(fn (Builder $w) => $w
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('personal_id', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like));
            }
        });
    }
}
