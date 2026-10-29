<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;

/** Creates permissions and default roles from config/permissions.php. Never detaches anything. */
class PermissionSyncer
{
    /** @return array{permissions: int, roles: int} number of newly created records */
    public function sync(): array
    {
        return [
            'permissions' => $this->syncPermissions(),
            'roles' => $this->syncRoles(),
        ];
    }

    /** Creates / updates every permission from config. Returns the number of new permissions. */
    public function syncPermissions(): int
    {
        $created = 0;

        foreach (config('permissions.permissions') as $group => $permissions) {
            foreach ($permissions as $name => $displayName) {
                $permission = Permission::updateOrCreate(['name' => $name], ['display_name' => $displayName, 'group' => $group]);
                $created += (int) $permission->wasRecentlyCreated;
            }
        }

        return $created;
    }

    /** Creates the default roles and attaches their default permissions. Returns the number of new roles. */
    public function syncRoles(): int
    {
        $created = 0;
        $allIds = Permission::pluck('id', 'name');

        foreach (config('permissions.roles') as $name => $definition) {
            $role = Role::firstOrCreate(['name' => $name], [
                'display_name' => $definition['display_name'],
                'description' => $definition['description'] ?? null,
                'is_system' => true,
            ]);
            $created += (int) $role->wasRecentlyCreated;

            $ids = in_array('*', $definition['permissions'], true)
                ? $allIds->values()
                : $allIds->only($definition['permissions'])->values();

            $role->permissions()->syncWithoutDetaching($ids->all());
        }

        return $created;
    }
}
