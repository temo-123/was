<?php

namespace App\Models;

use App\Models\Concerns\HasRolesAndPermissions;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar', 'phone', 'personal_id', 'birth_date', 'is_active', 'email_verified_at'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRolesAndPermissions, Notifiable, SoftDeletes;

    /** Same as the DB default, so new (not yet reloaded) models are active too. */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function visitorLink(): HasOne
    {
        return $this->hasOne(VisitorUser::class);
    }

    /** The visitor record (gym card) linked to this account, if any. */
    public function visitor(): HasOneThrough
    {
        return $this->hasOneThrough(Visitor::class, VisitorUser::class, 'user_id', 'id', 'id', 'visitor_id');
    }

    public function canAccessAdmin(): bool
    {
        return $this->is_active && $this->hasPermission('admin.access');
    }

    public function hasPassword(): bool
    {
        return ! empty($this->password);
    }

    public function avatarUrl(): string
    {
        return $this->avatar ?: 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($this->email))).'?d=mp&s=160';
    }
}
