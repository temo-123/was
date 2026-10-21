<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Age brackets: min_age inclusive, max_age exclusive (null = no upper limit).
        Schema::create('age_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('name_ka')->nullable();
            $table->unsignedTinyInteger('min_age');
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Time of day bands: start inclusive, end exclusive.
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('name_ka')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Single visit, monthly subscription, ...
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('name_ka')->nullable();
            // null = one-time entry; otherwise it's a subscription valid for N days.
            $table->unsignedSmallInteger('duration_days')->nullable();
            // null = unlimited entries during the subscription period.
            $table->unsignedSmallInteger('visits_limit')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Regular / member, etc. Lets one age group have several price columns.
        Schema::create('visitor_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('name_ka')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The price list matrix. visitor_category_id = null means "any category".
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('age_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['age_group_id', 'time_slot_id', 'ticket_type_id', 'visitor_category_id'], 'price_rules_unique');
        });

        // Multipliers applied on top of the matrix price (e.g. 1.10 = +10%, 0.80 = -20%).
        // Every null condition means "applies to all".
        Schema::create('price_coefficients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('coefficient', 6, 3)->default(1);
            $table->foreignId('age_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('time_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visitor_category_id')->nullable()->constrained()->nullOnDelete();
            $table->json('weekdays')->nullable(); // ISO weekdays: 1 = Monday ... 7 = Sunday
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_coefficients');
        Schema::dropIfExists('price_rules');
        Schema::dropIfExists('visitor_categories');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('time_slots');
        Schema::dropIfExists('age_groups');
    }
};
