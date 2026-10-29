<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['title', 'starts_at', 'location', 'notes', 'is_open', 'created_by'])]
class ClubMeeting extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'is_open' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $meeting) => $meeting->token ??= Str::random(40));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ClubAttendance::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(ClubMember::class, 'club_meeting_attendances')->withPivot('checked_in_at', 'method');
    }

    /** Current QR window number; the QR code changes when it changes. */
    public static function currentWindow(): int
    {
        $rotation = config('club.qr_rotation_seconds');

        return $rotation > 0 ? intdiv(time(), $rotation) : 0;
    }

    /** Short code put in the QR URL for the given window. */
    public function checkinCode(?int $window = null): string
    {
        $window ??= static::currentWindow();

        return substr(hash_hmac('sha256', $this->id.'|'.$window, $this->token.config('app.key')), 0, 12);
    }

    /** Accepts the current code and the ones replaced within the grace period. */
    public function isValidCheckinCode(?string $code): bool
    {
        if (! is_string($code) || $code === '') {
            return false;
        }

        $rotation = config('club.qr_rotation_seconds');
        $current = static::currentWindow();
        $oldest = $rotation > 0 ? $current - (int) ceil(config('club.qr_grace_seconds') / $rotation) : $current;

        for ($window = $current; $window >= $oldest; $window--) {
            if (hash_equals($this->checkinCode($window), $code)) {
                return true;
            }
        }

        return false;
    }
}
