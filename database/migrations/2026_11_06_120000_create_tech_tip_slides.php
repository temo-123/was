<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tech tips become slide shows: start screen (title, short description, cover) → slides one by one.
 * What every language shares (cover, slide layout, pictures, note colour, "more info" link) is stored once;
 * only the texts are per language. The blocks of the previous version are turned into slides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tech_tips', function (Blueprint $table) {
            $table->string('image')->nullable()->after('show_warning'); // cover, path on the "local" disk
        });

        Schema::create('tech_tip_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tech_tip_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('layout', 20); // App\Services\Tips\TipSlides::LAYOUTS
            $table->json('media')->nullable(); // [{id, src, mark: good|bad|null}]
            $table->boolean('light')->default(false); // drawings on a white background
            $table->string('note_variant', 10)->nullable(); // info|tip|warning|danger, null = no note box
            $table->foreignId('link_tip_id')->nullable()->constrained('tech_tips')->nullOnDelete(); // "more info"
            $table->timestamps();

            $table->index(['tech_tip_id', 'position']);
        });

        Schema::create('tech_tip_slide_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tech_tip_slide_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title')->nullable();
            $table->text('body')->nullable(); // Markdown
            $table->text('note')->nullable(); // Markdown, the note box
            $table->json('captions')->nullable(); // {media id: caption}
            $table->timestamps();

            $table->unique(['tech_tip_slide_id', 'locale']);
        });

        $this->convertBlocks();

        Schema::table('tech_tip_articles', function (Blueprint $table) {
            $table->dropColumn(['blocks', 'image']);
        });
    }

    public function down(): void
    {
        Schema::table('tech_tip_articles', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('short_description');
            $table->string('image')->nullable()->after('blocks');
        });
        Schema::dropIfExists('tech_tip_slide_texts');
        Schema::dropIfExists('tech_tip_slides');
        Schema::table('tech_tips', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    /** Blocks → slides. The first language gives the slides and pictures, the others add their texts slide by slide. */
    private function convertBlocks(): void
    {
        foreach (DB::table('tech_tips')->pluck('id') as $tipId) {
            $articles = DB::table('tech_tip_articles')->where('tech_tip_id', $tipId)->orderByRaw("locale = 'ka' desc")->get();
            if ($articles->isEmpty()) {
                continue;
            }
            DB::table('tech_tips')->where('id', $tipId)->update(['image' => $articles->firstWhere('image', '!=', null)?->image]);

            $perLocale = $articles->mapWithKeys(fn ($a) => [$a->locale => $this->slidesFromBlocks(json_decode($a->blocks ?? '[]', true) ?: [])]);
            foreach ($perLocale->first() as $position => $slide) {
                $slideId = DB::table('tech_tip_slides')->insertGetId([
                    'tech_tip_id' => $tipId, 'position' => $position, 'layout' => $slide['layout'],
                    'media' => json_encode($slide['media']), 'light' => $slide['light'], 'note_variant' => $slide['note_variant'],
                    'link_tip_id' => $slide['link'] && DB::table('tech_tips')->where('id', $slide['link'])->exists() ? $slide['link'] : null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($perLocale as $locale => $slides) {
                    $own = $slides[$position] ?? null;
                    if (! $own) {
                        continue;
                    }
                    // Captions belong to the first language's pictures, matched by their order.
                    $captions = [];
                    foreach ($slide['media'] as $i => $item) {
                        if (filled($own['captionList'][$i] ?? null)) {
                            $captions[$item['id']] = $own['captionList'][$i];
                        }
                    }
                    DB::table('tech_tip_slide_texts')->insert([
                        'tech_tip_slide_id' => $slideId, 'locale' => $locale,
                        'title' => $own['title'] ?: null, 'body' => implode("\n\n", $own['body']) ?: null, 'note' => $own['note'] ?: null,
                        'captions' => json_encode((object) $captions), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function slidesFromBlocks(array $blocks): array
    {
        $slides = [];
        $current = null;
        $new = fn () => ['layout' => 'text', 'media' => [], 'captionList' => [], 'light' => false, 'note_variant' => null, 'link' => null, 'title' => '', 'body' => [], 'note' => ''];
        $flush = function () use (&$slides, &$current) {
            if ($current && ($current['media'] || $current['title'] !== '' || $current['body'] || $current['note'] !== '')) {
                $slides[] = $current;
            }
            $current = null;
        };
        $media = function (array $items, string $layout, bool $light) use (&$current, $new, $flush) {
            if ($current && $current['media']) {
                $flush();
            }
            $current ??= $new();
            $current['media'] = array_map(fn ($i) => ['id' => Str::random(8), 'src' => $i['src'], 'mark' => $i['mark'] ?? null], $items);
            $current['captionList'] = array_map(fn ($i) => $i['caption'] ?? '', $items);
            $current['layout'] = $layout;
            $current['light'] = $light;
        };

        foreach ($blocks as $b) {
            switch ($b['type'] ?? null) {
                case 'heading':
                    $flush();
                    $current = $new();
                    $current['title'] = $b['text'] ?? '';
                    break;
                case 'text':
                    $current ??= $new();
                    $current['body'][] = $b['text'] ?? '';
                    break;
                case 'image':
                    $media([['src' => $b['src'], 'caption' => $b['caption'] ?? '']], 'picture_text', (bool) ($b['light'] ?? false));
                    break;
                case 'sequence':
                    $media($b['items'] ?? [], 'sequence', (bool) ($b['light'] ?? false));
                    break;
                case 'compare':
                    $media(array_values(array_filter([
                        ($b['good']['src'] ?? null) ? $b['good'] + ['mark' => 'good'] : null,
                        ($b['bad']['src'] ?? null) ? $b['bad'] + ['mark' => 'bad'] : null,
                    ])), 'compare', (bool) ($b['light'] ?? false));
                    break;
                case 'video':
                    $media([['src' => $b['src'], 'caption' => $b['caption'] ?? '']], 'video', false);
                    break;
                case 'step':
                    $flush();
                    $current = $new();
                    $current['title'] = $b['title'] ?? '';
                    $current['body'] = array_filter([$b['text'] ?? '']);
                    $current['link'] = $b['link'] ?? null;
                    if ($b['src'] ?? null) {
                        $current['media'] = [['id' => Str::random(8), 'src' => $b['src'], 'mark' => null]];
                        $current['captionList'] = [''];
                        $current['layout'] = 'picture_text';
                        $current['light'] = (bool) ($b['light'] ?? false);
                    }
                    break;
                case 'callout':
                    $current ??= $new();
                    if ($current['note'] !== '') {
                        $flush();
                        $current = $new();
                    }
                    $current['note_variant'] = $b['variant'] ?? 'info';
                    $current['note'] = trim((filled($b['title'] ?? null) ? "**{$b['title']}**\n\n" : '').($b['text'] ?? ''));
                    break;
                case 'link':
                    $current ??= $new();
                    $current['link'] ??= $b['tip'] ?? null;
                    break;
            }
        }
        $flush();

        return $slides;
    }
};
