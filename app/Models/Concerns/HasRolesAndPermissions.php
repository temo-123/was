<?php

namespace App\Models\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRolesAndPermissions
{
    /** Per-request cache of the user's effective permission names. */
    protected ?Collection $permissionNamesCache = null;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')->withPivot('created_at');
    }

    /** Permissions granted directly to the user (not through a role). */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permission')->withPivot('created_at');
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return $this->roles->contains(fn (Role $role) => in_array($role->name, $roles, true));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('permissions.super_admin_role'));
    }

    /** All permission names the user has, from roles and direct grants. */
    public function allPermissionNames(): Collection
    {
        if ($this->permissionNamesCache !== null) {
            return $this->permissionNamesCache;
        }

        $this->loadMissing('roles.permissions', 'permissions');

        return $this->permissionNamesCache = $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->merge($this->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isSuperAdmin() || $this->allPermissionNames()->contains($permission);
    }

    public function assignRole(string|Role ...$roles): static
    {
        $ids = collect($roles)
            ->map(fn ($role) => $role instanceof Role ? $role : Role::where('name', $role)->firstOrFail())
            ->mapWithKeys(fn (Role $role) => [$role->id => ['created_at' => now()]]);

        $this->roles()->syncWithoutDetaching($ids->all());
        $this->flushPermissionCache();

        return $this;
    }

    public function syncRoles(array $roleIds): static
    {
        $this->roles()->sync(collect($roleIds)->mapWithKeys(fn ($id) => [$id => ['created_at' => now()]])->all());
        $this->flushPermissionCache();

        return $this;
    }

    public function syncDirectPermissions(array $permissionIds): static
    {
        $this->permissions()->sync(collect($permissionIds)->mapWithKeys(fn ($id) => [$id => ['created_at' => now()]])->all());
        $this->flushPermissionCache();

        return $this;
    }

    public function flushPermissionCache(): void
    {
        $this->permissionNamesCache = null;
        $this->unsetRelation('roles');
        $this->unsetRelation('permissions');
    }
}
