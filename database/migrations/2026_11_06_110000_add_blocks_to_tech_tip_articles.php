<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tech tips are built from blocks (heading, text, picture, picture sequence, numbered step, correct/wrong,
 * note box, link to another tip, video) instead of one Markdown text. The old text becomes a text block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tech_tip_articles', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('short_description');
        });

        DB::table('tech_tip_articles')->orderBy('id')->each(function ($article) {
            $blocks = filled($article->body) ? [['id' => 'b'.$article->id, 'type' => 'text', 'text' => $article->body]] : [];
            DB::table('tech_tip_articles')->where('id', $article->id)->update(['blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE)]);
        });

        Schema::table('tech_tip_articles', function (Blueprint $table) {
            $table->dropColumn('body');
        });

        Schema::table('tech_tips', function (Blueprint $table) {
            // The "these tips don't replace training" box at the top of the tip.
            $table->boolean('show_warning')->default(true)->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('tech_tip_articles', function (Blueprint $table) {
            $table->longText('body')->nullable()->after('short_description');
        });

        DB::table('tech_tip_articles')->orderBy('id')->each(function ($article) {
            $texts = collect(json_decode($article->blocks ?? '[]', true))->where('type', 'text')->pluck('text')->implode("\n\n");
            DB::table('tech_tip_articles')->where('id', $article->id)->update(['body' => $texts]);
        });

        Schema::table('tech_tip_articles', function (Blueprint $table) {
            $table->dropColumn('blocks');
        });
        Schema::table('tech_tips', function (Blueprint $table) {
            $table->dropColumn('show_warning');
        });
    }
};
