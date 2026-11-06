<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rental gear (climbing shoes, chalk…) added to a visit, client statuses with a discount (VIP −50 %, Gold −100 %)
 * and rewards for every N-th visit. A visit keeps a snapshot of all of it, like it does for the entry price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ka')->nullable();
            $table->decimal('price', 8, 2);
            $table->boolean('has_sizes')->default(false); // climbing shoes: staff writes the size
            $table->boolean('is_returnable')->default(true); // shoes come back, chalk doesn't
            $table->unsignedSmallInteger('stock')->nullable(); // pieces the gym has, null = not counted
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // VIP, Gold…: a discount on the entry (and optionally on rental gear).
        Schema::create('client_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ka')->nullable();
            $table->string('color', 7)->default('#f5b83d');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('rental_discount_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->foreignId('client_tier_id')->nullable()->after('visitor_category_id')->constrained()->nullOnDelete();
            $table->date('client_tier_until')->nullable()->after('client_tier_id'); // null = no end
        });

        // Every N-th visit: free entry, a discount, or a free rental item.
        Schema::create('visit_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ka')->nullable();
            $table->unsignedSmallInteger('every_visits');
            $table->string('type', 20); // App\Models\VisitReward::TYPES
            $table->decimal('value', 5, 2)->nullable(); // discount %
            $table->foreignId('rental_item_id')->nullable()->constrained()->nullOnDelete(); // free_rental
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('visitor_visits', function (Blueprint $table) {
            // `price` stays the entry price (after discounts); `total_price` = entry + rental gear = money for this visit.
            $table->unsignedInteger('visit_number')->nullable()->after('visitor_id');
            $table->foreignId('client_tier_id')->nullable()->after('visitor_category_id')->constrained()->nullOnDelete();
            $table->decimal('discount_percent', 5, 2)->default(0)->after('coefficient'); // the client status discount
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_percent'); // status + rewards, on the entry
            $table->json('rewards')->nullable()->after('discount_amount'); // [{id, name, name_ka, type, value}]
            $table->decimal('rental_price', 10, 2)->default(0)->after('price');
            $table->decimal('total_price', 10, 2)->default(0)->after('rental_price');
        });
        DB::table('visitor_visits')->update(['total_price' => DB::raw('price')]);

        Schema::create('visit_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // snapshot
            $table->string('name_ka')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('size', 16)->nullable();
            $table->decimal('unit_price', 8, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('price', 10, 2); // what was paid for this line
            $table->boolean('is_reward')->default(false); // given for free by a visit reward
            $table->boolean('is_returnable')->default(true);
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_returnable', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_rentals');
        Schema::table('visitor_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_tier_id');
            $table->dropColumn(['visit_number', 'discount_percent', 'discount_amount', 'rewards', 'rental_price', 'total_price']);
        });
        Schema::dropIfExists('visit_rewards');
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_tier_id');
            $table->dropColumn('client_tier_until');
        });
        Schema::dropIfExists('client_tiers');
        Schema::dropIfExists('rental_items');
    }
};
