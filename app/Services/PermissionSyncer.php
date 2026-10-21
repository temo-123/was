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
        $created = ['permissions' => 0, 'roles' => 0];

        foreach (config('permissions.permissions') as $group => $permissions) {
            foreach ($permissions as $name => $displayName) {
                $permission = Permission::updateOrCreate(['name' => $name], ['display_name' => $displayName, 'group' => $group]);
                $created['permissions'] += (int) $permission->wasRecentlyCreated;
            }
        }

        $allIds = Permission::pluck('id', 'name');

        foreach (config('permissions.roles') as $name => $definition) {
            $role = Role::firstOrCreate(['name' => $name], [
                'display_name' => $definition['display_name'],
                'description' => $definition['description'] ?? null,
                'is_system' => true,
            ]);
            $created['roles'] += (int) $role->wasRecentlyCreated;

            $ids = in_array('*', $definition['permissions'], true)
                ? $allIds->values()
                : $allIds->only($definition['permissions'])->values();

            $role->permissions()->syncWithoutDetaching($ids->all());
        }

        return $created;
    }
}
