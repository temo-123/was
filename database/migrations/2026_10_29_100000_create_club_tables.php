<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sport club members (separate from gym visitors).
        Schema::create('club_members', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('personal_id', 32)->nullable()->unique();
            $table->string('phone', 32)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->date('joined_on')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });

        Schema::create('club_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('starts_at');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->string('token', 64)->unique(); // used in the check-in URL behind the QR code
            $table->boolean('is_open')->default(true); // members can check in
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('starts_at');
        });

        Schema::create('club_meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->string('method', 16)->default('qr'); // qr, manual
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['club_meeting_id', 'club_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_meeting_attendances');
        Schema::dropIfExists('club_meetings');
        Schema::dropIfExists('club_members');
    }
};
