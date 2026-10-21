<?php

namespace Database\Seeders;

use App\Services\PermissionSyncer;
use Illuminate\Database\Seeder;

/** Roles (admin, worker, user) and permissions from config/permissions.php. */
class RoleSeeder extends Seeder
{
    public function run(PermissionSyncer $syncer): void
    {
        $created = $syncer->sync();

        $this->command?->info("Roles & permissions synced (new permissions: {$created['permissions']}, new roles: {$created['roles']}).");
    }
}
