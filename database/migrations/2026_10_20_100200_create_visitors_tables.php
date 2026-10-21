<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('personal_id', 32)->unique(); // ID / passport number
            $table->date('birth_date');
            $table->string('phone', 32);
            $table->string('email')->nullable()->index();
            $table->text('comment')->nullable();
            $table->foreignId('visitor_category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('terms_accepted')->default(false);
            $table->timestamp('terms_accepted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index('phone');
        });

        // Link between a registered user account and a visitor record (one to one).
        Schema::create('visitor_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('linked_via', 32)->default('manual'); // manual, personal_id, email
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('visitor_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained();
            $table->foreignId('time_slot_id')->constrained();
            $table->foreignId('age_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visitor_category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('visits_limit')->nullable();
            $table->unsignedSmallInteger('visits_used')->default(0);
            $table->decimal('base_price', 10, 2);
            $table->decimal('coefficient', 6, 3)->default(1);
            $table->decimal('price', 10, 2);
            $table->string('payment_method', 16)->default('cash');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['visitor_id', 'ends_on']);
        });

        Schema::create('visitor_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->dateTime('entered_at')->index();
            // Snapshot of how the price was calculated at the moment of entry.
            $table->unsignedTinyInteger('age_at_visit');
            $table->foreignId('age_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('time_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visitor_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visitor_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('coefficient', 6, 3)->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('price_overridden')->default(false);
            $table->string('payment_method', 16)->default('cash'); // cash, card, transfer, subscription, free
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64)->index();
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('visitor_visits');
        Schema::dropIfExists('visitor_subscriptions');
        Schema::dropIfExists('visitor_user');
        Schema::dropIfExists('visitors');
    }
};
