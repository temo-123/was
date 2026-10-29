<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'first_name', 'last_name', 'personal_id', 'phone', 'email', 'birth_date', 'gender',
    'emergency_contact_name', 'emergency_contact_phone', 'joined_on', 'comment', 'is_active', 'is_pending', 'created_by',
])]
class ClubMember extends Model
{
    use HasFactory;

    public const GENDERS = ['female' => 'Female', 'male' => 'Male'];

    /** Everything a member card must have: asked at self-registration, and after check-in when something is missing. */
    public const PROFILE_FIELDS = [
        'first_name', 'last_name', 'personal_id', 'birth_date', 'gender', 'phone', 'email',
        'emergency_contact_name', 'emergency_contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'joined_on' => 'date',
            'is_active' => 'boolean',
            'is_pending' => 'boolean',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /** @return list<string> PROFILE_FIELDS that are still empty */
    public function missingProfileFields(): array
    {
        return array_values(array_filter(self::PROFILE_FIELDS, fn (string $field) => blank($this->{$field})));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ClubAttendance::class);
    }

    /** Exactly this first and last name, in either order (the collation ignores upper/lower case). */
    public function scopeNameMatch(Builder $query, string $firstName, string $lastName): Builder
    {
        [$firstName, $lastName] = [trim($firstName), trim($lastName)];

        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $w) => $w->where('first_name', $firstName)->where('last_name', $lastName))
            ->orWhere(fn (Builder $w) => $w->where('first_name', $lastName)->where('last_name', $firstName)));
    }

    /**
     * What a member typed on the check-in page: an email, ID number or phone, or the full name
     * ("Nino Beridze" or "Beridze Nino"). Exact matches only — the page doesn't list anybody, so a part
     * of a name must not be enough to check in as somebody else.
     */
    public function scopeCheckinMatch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $digits = preg_replace('/[\s\-().+]/', '', $term);

        if (str_contains($term, '@')) {
            return $query->where('email', mb_strtolower($term));
        }
        if (ctype_digit($digits)) {
            return $query->where(fn (Builder $q) => $q->where('personal_id', $digits)->orWhere('phone', $digits));
        }

        $words = preg_split('/\s+/', preg_replace('/\s+/', ' ', $term), 2);

        return count($words) === 2 ? $query->nameMatch($words[0], $words[1]) : $query->whereRaw('1 = 0');
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
