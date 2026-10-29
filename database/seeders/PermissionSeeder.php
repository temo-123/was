<?php

namespace Database\Seeders;

use App\Services\PermissionSyncer;
use Illuminate\Database\Seeder;

/** Default permissions from config/permissions.php. Safe to run more than once. */
class PermissionSeeder extends Seeder
{
    public function run(PermissionSyncer $syncer): void
    {
        $created = $syncer->syncPermissions();

        $this->command?->info("Permissions synced (new: {$created}).");
    }
}
