<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A tip itself (shared by every language): topic, level, order on the tablet.
        Schema::create('tech_tips', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32)->index(); // key of config('tips.categories')
            $table->unsignedTinyInteger('level')->nullable(); // config('tips.levels'), null = everyone
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('views')->default(0); // opened on the tablet
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        // The tip's text in one language (ka / en): one row per language.
        Schema::create('tech_tip_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tech_tip_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->string('short_description', 500)->nullable();
            $table->longText('body'); // Markdown
            $table->string('image')->nullable(); // path on the "local" disk
            $table->timestamps();

            $table->unique(['tech_tip_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_tip_articles');
        Schema::dropIfExists('tech_tips');
    }
};
