<?php

namespace Database\Seeders;

use App\Services\PermissionSyncer;
use Illuminate\Database\Seeder;

/** Roles (admin, worker, user) with their default permissions from config/permissions.php. Run after PermissionSeeder. */
class RoleSeeder extends Seeder
{
    public function run(PermissionSyncer $syncer): void
    {
        $created = $syncer->syncRoles();

        $this->command?->info("Roles synced (new: {$created}).");
    }
}
