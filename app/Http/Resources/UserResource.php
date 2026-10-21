<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'phone' => $this->phone,
            'personal_id' => $this->personal_id,
            'birth_date' => $this->birth_date?->toDateString(),
            'avatar' => $this->avatarUrl(),
            'roles' => $this->roles->pluck('name'),
            'permissions' => $this->isSuperAdmin() ? ['*'] : $this->allPermissionNames(),
            'visitor' => $this->whenLoaded('visitor', fn () => $this->visitor ? new VisitorResource($this->visitor) : null),
        ];
    }
}
