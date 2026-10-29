<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asked at self-registration, and after check-in from members whose card is incomplete.
        Schema::table('club_members', function (Blueprint $table) {
            $table->string('gender', 16)->nullable()->after('birth_date');
            $table->string('emergency_contact_name', 150)->nullable()->after('email');
            $table->string('emergency_contact_phone', 32)->nullable()->after('emergency_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            $table->dropColumn(['gender', 'emergency_contact_name', 'emergency_contact_phone']);
        });
    }
};
