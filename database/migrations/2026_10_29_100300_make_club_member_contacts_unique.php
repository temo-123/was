<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A phone number or email belongs to one member, so check-in can find people by them.
        Schema::table('club_members', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['email']);
            $table->unique('phone');
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropUnique(['email']);
            $table->index('phone');
            $table->index('email');
        });
    }
};
