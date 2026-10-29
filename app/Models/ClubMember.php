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
    'first_name', 'last_name', 'personal_id', 'phone', 'email', 'birth_date', 'joined_on', 'comment',
    'is_active', 'is_pending', 'created_by',
])]
class ClubMember extends Model
{
    use HasFactory;

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ClubAttendance::class);
    }

    /** Last digits of the phone, to tell apart members with the same name on the check-in page. */
    protected function phoneHint(): Attribute
    {
        return Attribute::get(fn () => $this->phone ? '•••'.substr($this->phone, -3) : null);
    }

    /** Members whose name starts with what was typed on the check-in page (in either order). */
    public function scopeNameMatch(Builder $query, string $firstName, string $lastName): Builder
    {
        $like = fn (string $text) => str_replace(['%', '_'], ['\%', '\_'], trim($text)).'%';

        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $w) => $w->where('first_name', 'like', $like($firstName))->where('last_name', 'like', $like($lastName)))
            ->orWhere(fn (Builder $w) => $w->where('first_name', 'like', $like($lastName))->where('last_name', 'like', $like($firstName))));
    }

    /**
     * What a member typed on the check-in page: an email, ID number or phone (exact match),
     * or a name ("Nino Beridze", "beri nin" or just one of the names, start of the word is enough).
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

        $words = preg_split('/\s+/', $term, 2);
        if (count($words) === 2) {
            return $query->nameMatch($words[0], $words[1]);
        }

        $like = str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like));
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
