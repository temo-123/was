<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wi-Fi shown on the QR screen for this meeting; empty = the default from config/club.php (CLUB_WIFI_*).
        Schema::table('club_meetings', function (Blueprint $table) {
            $table->string('wifi_name', 64)->nullable()->after('location');
            $table->string('wifi_password', 128)->nullable()->after('wifi_name');
        });
    }

    public function down(): void
    {
        Schema::table('club_meetings', function (Blueprint $table) {
            $table->dropColumn(['wifi_name', 'wifi_password']);
        });
    }
};
