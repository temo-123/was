<?php

namespace App\Console\Commands;

use App\Services\PermissionSyncer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync')]
#[Description('Create missing permissions and default roles from config/permissions.php')]
class SyncPermissions extends Command
{
    public function handle(PermissionSyncer $syncer): int
    {
        $created = $syncer->sync();

        $this->info("Permissions synced. New permissions: {$created['permissions']}, new roles: {$created['roles']}.");

        return self::SUCCESS;
    }
}
