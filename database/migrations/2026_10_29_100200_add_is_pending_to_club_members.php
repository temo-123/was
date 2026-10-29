<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Members who registered themselves at a meeting wait here until an admin approves or merges them.
        Schema::table('club_members', function (Blueprint $table) {
            $table->boolean('is_pending')->default(false)->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            $table->dropColumn('is_pending');
        });
    }
};
