<?php

use App\Services\PermissionSyncer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionSyncer::class)->sync();
    }

    public function down(): void
    {
        //
    }
};
