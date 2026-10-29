<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'club_meeting_attendances')]
#[Fillable(['club_meeting_id', 'club_member_id', 'checked_in_at', 'method', 'ip_address', 'created_by'])]
class ClubAttendance extends Model
{
    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ClubMeeting::class, 'club_meeting_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
