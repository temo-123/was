<?php

namespace App\Services\Tips;

use App\Models\TechTip;
use App\Models\TechTipSlide;
use App\Models\TechTipSlideText;
use Illuminate\Support\Str;

/**
 * The slides of a tech tip. Layouts and the pictures they use:
 *
 *   picture_text  1 picture on the left, text on the right (default)
 *   picture       1 big picture, text under it
 *   text          only text
 *   sequence      2–6 pictures with arrows (a movement step by step), text under them
 *   compare       2 pictures: correct ✓ and wrong ✗
 *   video         1 video (mp4 / webm stored here: the gym may have no internet)
 *
 * A slide as the editor (TipEditor.vue) sends it and clean() returns it:
 *   {id, layout, light, media: [{id, src, mark}], note: info|tip|warning|danger|null, link: tip id|null,
 *    texts: {ka: {title, body, note, captions: {media id: caption}}, en: {...}}}
 * body and note are Markdown; a picture inside them: ![caption](media:<file> "left|right").
 */
class TipSlides
{
    public const LAYOUTS = ['picture_text', 'picture', 'text', 'sequence', 'compare', 'video'];

    public const NOTES = ['info', 'tip', 'warning', 'danger'];

    public const MAX_SLIDES = 60;

    /** A picture inside a text: ![caption](media:<file>) or ![caption](media:<file> "left"), see markdown(). */
    private const INLINE_IMAGE = '/!\[([^\]]*)\]\(media:('.TipMedia::FILE_PATTERN.')(?:\s+"(left|right)")?\)/';

    public function __construct(private TipMedia $media) {}

    /** The editor's slides, keeping only known fields and uploaded files; empty slides are dropped. */
    public function clean(mixed $slides): array
    {
        if (! is_array($slides)) {
            return [];
        }

        $clean = [];
        foreach (array_slice(array_values($slides), 0, self::MAX_SLIDES) as $slide) {
            if (is_array($slide) && ($slide = $this->cleanSlide($slide)) !== null) {
                $clean[] = $slide;
            }
        }

        return $clean;
    }

    /** A saved slide in the editor's shape (for the edit form and the tablet). */
    public function fromModel(TechTipSlide $slide): array
    {
        return [
            'id' => 's'.$slide->id,
            'layout' => $slide->layout,
            'light' => $slide->light,
            'media' => $slide->media ?? [],
            'note' => $slide->note_variant,
            'link' => $slide->link_tip_id,
            'texts' => $slide->texts->mapWithKeys(fn (TechTipSlideText $t) => [$t->locale => [
                'title' => $t->title ?? '',
                'body' => $t->body ?? '',
                'note' => $t->note ?? '',
                'captions' => $t->captions ?? [],
            ]])->all(),
        ];
    }

    /** What the tablet gets for one slide: URLs instead of paths, Markdown as HTML, only languages with text. */
    public function render(array $slide): array
    {
        return [
            'layout' => $slide['layout'],
            'light' => $slide['light'],
            'note' => $slide['note'],
            'link' => $slide['link'],
            'media' => array_map(fn ($m) => [
                'id' => $m['id'],
                'src' => $this->media->url($m['src']),
                'mark' => $m['mark'],
                'video' => $this->media->isVideo($m['src']),
            ], $slide['media']),
            'texts' => collect($slide['texts'])->map(fn ($t) => [
                'title' => $t['title'],
                'html' => $t['body'] !== '' ? $this->markdown($t['body']) : '',
                'note' => $t['note'] !== '' ? $this->markdown($t['note']) : '',
                'captions' => (object) $t['captions'],
            ])->all(),
        ];
    }

    /** Words of a language in the slides, for "3 min read". */
    public function words(array $slides, string $locale): int
    {
        $text = collect($slides)->map(fn ($s) => implode(' ', [
            $s['texts'][$locale]['title'] ?? '', $s['texts'][$locale]['body'] ?? '', $s['texts'][$locale]['note'] ?? '',
            ...array_values($s['texts'][$locale]['captions'] ?? []),
        ]))->implode(' ');

        return count(preg_split('/\s+/u', strip_tags($text), -1, PREG_SPLIT_NO_EMPTY));
    }

    /** Files used by these slides ("tech-tips/…"): their pictures and pictures inside their texts. */
    public function mediaPaths(array $slides): array
    {
        $paths = [];
        foreach ($slides as $slide) {
            array_push($paths, ...array_column($slide['media'] ?? [], 'src'));
            foreach ($slide['texts'] ?? [] as $text) {
                preg_match_all(self::INLINE_IMAGE, ($text['body'] ?? '').' '.($text['note'] ?? ''), $matches);
                array_push($paths, ...array_map(fn ($file) => TipMedia::DIR.'/'.$file, $matches[2]));
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /** Every file any tip uses (covers + slides). */
    public function usedPaths(): array
    {
        $slides = TechTipSlide::with('texts')->get()->map($this->fromModel(...))->all();

        return array_values(array_unique([...TechTip::whereNotNull('image')->pluck('image')->all(), ...$this->mediaPaths($slides)]));
    }

    /**
     * Markdown → HTML; raw HTML typed into the text is dropped. Pictures inside the text ("media:<file>", put there
     * by the editor's picture button) become <figure>s: full width, or small on the left / right of the text.
     * Pictures that aren't uploaded files are removed.
     */
    public function markdown(string $text): string
    {
        $figures = [];
        $text = preg_replace_callback(self::INLINE_IMAGE, function ($m) use (&$figures) {
            $path = $this->media->pathOf($m[2]);
            if (! $path || $this->media->isVideo($path)) {
                return '';
            }
            $caption = trim($m[1]);
            $key = 'TIPFIGURE'.count($figures).'X';
            $figures[$key] = '<figure class="tt-inline-pic '.($m[3] ?? 'full').'"><img src="'.e($this->media->url($path)).'" alt="'.e($caption).'">'
                .($caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '').'</figure>';

            return "\n\n$key\n\n"; // its own paragraph, swapped for the <figure> below
        }, $text);
        $text = preg_replace('/!\[[^\]]*\]\((?!https?:)[^)]*\)/', '', $text); // any other local picture syntax

        $html = Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return strtr($html, collect($figures)->mapWithKeys(fn ($figure, $key) => ["<p>$key</p>" => $figure, $key => $figure])->all());
    }

    private function cleanSlide(array $s): ?array
    {
        $layout = in_array($s['layout'] ?? null, self::LAYOUTS, true) ? $s['layout'] : 'picture_text';

        // Pictures (or the video) the layout uses.
        $items = [];
        foreach (array_slice((array) ($s['media'] ?? []), 0, 6) as $m) {
            $path = is_array($m) ? $this->media->pathOf($m['src'] ?? null) : null;
            if ($path && $this->media->isVideo($path) === ($layout === 'video')) {
                $items[] = ['id' => $this->id($m['id'] ?? null), 'src' => $path, 'mark' => null];
            }
        }
        $items = match ($layout) {
            'text' => [],
            'picture_text', 'picture', 'video' => array_slice($items, 0, 1),
            'compare' => array_slice($items, 0, 2),
            'sequence' => $items,
        };
        if ($layout === 'compare') {
            foreach ($items as $i => &$item) {
                $item['mark'] = $i === 0 ? 'good' : 'bad';
            }
            unset($item);
        }
        $mediaIds = array_column($items, 'id');

        $texts = [];
        foreach (array_keys(config('app.locales')) as $locale) {
            $t = (array) ($s['texts'][$locale] ?? []);
            $captions = [];
            foreach ((array) ($t['captions'] ?? []) as $id => $caption) {
                if (in_array((string) $id, $mediaIds, true) && ($caption = $this->string($caption, 500)) !== '') {
                    $captions[(string) $id] = $caption;
                }
            }
            $text = [
                'title' => $this->string($t['title'] ?? null, 255),
                'body' => $this->string($t['body'] ?? null, 20000),
                'note' => $this->string($t['note'] ?? null, 5000),
                'captions' => $captions,
            ];
            if ($text['title'] !== '' || $text['body'] !== '' || $text['note'] !== '' || $captions) {
                $texts[$locale] = $text;
            }
        }

        if (! $items && ! $texts) {
            return null;
        }

        $note = in_array($s['note'] ?? null, self::NOTES, true) ? $s['note'] : null;

        return [
            'id' => $this->id($s['id'] ?? null),
            'layout' => $layout,
            'light' => filter_var($s['light'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'media' => $items,
            'note' => $note,
            'link' => is_numeric($s['link'] ?? null) && (int) $s['link'] > 0 ? (int) $s['link'] : null,
            'texts' => $note ? $texts : array_map(fn ($t) => ['note' => ''] + $t, $texts),
        ];
    }

    private function id(mixed $value): string
    {
        return is_string($value) && preg_match('/^[\w-]{1,40}$/', $value) ? $value : Str::random(10);
    }

    private function string(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr(trim(str_replace("\r\n", "\n", $value)), 0, $max) : '';
    }
}
