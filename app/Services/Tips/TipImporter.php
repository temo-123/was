<?php

namespace App\Services\Tips;

use App\Models\TechTip;
use App\Models\TechTipArticle;
use App\Models\TechTipSlide;
use App\Models\TechTipSlideText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creates tech tips from a JSON file (`php artisan tips:import`, TechTipSeeder). Format, one object per tip:
 *
 *   {"key": "figure-eight",                      // optional, other slides link to it with "link": "figure-eight"
 *    "category": "belaying", "level": 1, "show_warning": true, "published": true,
 *    "cover": "https://…/cover.jpg",             // picture: http(s) link, or a file path relative to the JSON file
 *    "articles": {"en": {"title": "…", "short": "…"}, "ka": {"title": "…", "short": "…"}},
 *    "slides": [
 *      {"layout": "picture_text",                 // TipSlides::LAYOUTS
 *       "media": ["https://…/1.jpg"],             // pictures (or 1 video file) of the layout
 *       "light": false, "note": "warning", "link": "other-key",
 *       "texts": {"en": {"title": "…", "body": "Markdown, may hold ![caption](https://…/x.jpg \"left\")",
 *                        "note": "…", "captions": ["caption of picture 1", "…"]},
 *                 "ka": {…}}}
 *    ]}
 *
 * Every picture is downloaded / copied and stored like an upload (TipMedia), the slides go through TipSlides::clean().
 */
class TipImporter
{
    /** Pictures written in a text: ![caption](address "left|right"), address = link or file path (not media:). */
    private const TEXT_IMAGE = '/!\[([^\]]*)\]\((?!media:)([^)\s]+)(?:\s+"(left|right)")?\)/';

    /** @var callable(string): void */
    private $log;

    private string $baseDir = '';

    /** @var array<string, string> address → stored file name, so a picture used twice is stored once */
    private array $stored = [];

    public function __construct(private TipMedia $media, private TipSlides $slides) {}

    /** Reads and imports a JSON file; returns the number of tips created. */
    public function importFile(string $file, bool $replace = false, ?callable $log = null): int
    {
        $data = json_decode((string) @file_get_contents($file), true);
        if (! is_array($data)) {
            throw new \InvalidArgumentException("$file is not a JSON list of tips: ".json_last_error_msg());
        }

        return $this->import(array_is_list($data) ? $data : ($data['tips'] ?? []), $replace, $log, dirname(realpath($file) ?: $file));
    }

    /**
     * $replace: ALL old tips (texts, slides, links) and ALL files in storage/app/private/tech-tips are deleted,
     * only the imported tips and their pictures remain.
     *
     * @param  array<int, array>  $tips
     * @param  string  $baseDir  folder that relative picture paths start from
     */
    public function import(array $tips, bool $replace = false, ?callable $log = null, string $baseDir = ''): int
    {
        $this->log = $log ?? fn () => null;
        $this->baseDir = $baseDir !== '' ? $baseDir : base_path();
        $this->stored = [];

        // Pictures are downloaded first (slow, may fail), the database is changed in one go afterwards.
        $prepared = [];
        foreach (array_values($tips) as $i => $tip) {
            if (! is_array($tip)) {
                continue;
            }
            ($this->log)('Tip '.($i + 1).': '.($tip['articles']['en']['title'] ?? $tip['articles']['ka']['title'] ?? $tip['key'] ?? '?'));
            $prepared[] = $this->prepare($tip, $i);
        }

        DB::transaction(function () use ($prepared, $replace) {
            if ($replace) {
                TechTipSlideText::query()->delete();
                TechTipSlide::query()->delete();
                TechTipArticle::query()->delete();
                TechTip::query()->delete();
            }

            $sort = (TechTip::max('sort_order') ?? 0) + 10;
            $byKey = [];
            $created = [];
            foreach ($prepared as $p) {
                $tip = TechTip::create($p['tip'] + ['sort_order' => $p['sort_order'] ?? $sort]);
                $sort += 10;
                foreach ($p['articles'] as $locale => $article) {
                    (new TechTipArticle(['locale' => $locale] + $article))->tip()->associate($tip)->save();
                }
                if ($p['key'] !== null) {
                    $byKey[$p['key']] = $tip->id;
                }
                $created[] = [$tip, $p];
            }

            // Slides after all tips exist, so "link" can point to any tip of the file.
            foreach ($created as [$tip, $p]) {
                foreach ($p['slides'] as $position => $slide) {
                    $link = $p['links'][$position] ?? null;
                    $linkId = is_numeric($link) ? (int) $link : ($byKey[$link] ?? null);
                    $model = $tip->slides()->create([
                        'position' => $position,
                        'layout' => $slide['layout'],
                        'media' => $slide['media'],
                        'light' => $slide['light'],
                        'note_variant' => $slide['note'],
                        'link_tip_id' => $linkId && $linkId !== $tip->id && TechTip::whereKey($linkId)->exists() ? $linkId : null,
                    ]);
                    foreach ($slide['texts'] as $locale => $text) {
                        $model->texts()->create([
                            'locale' => $locale,
                            'title' => $text['title'] ?: null,
                            'body' => $text['body'] ?: null,
                            'note' => $text['note'] ?: null,
                            'captions' => (object) $text['captions'],
                        ]);
                    }
                }
            }
        });

        // Every file no tip uses is deleted now: with $replace that is every old picture, without it nothing of the others.
        $this->media->sweep($replace ? Storage::disk('local')->files(TipMedia::DIR) : []);

        return count($prepared);
    }

    private function prepare(array $tip, int $index): array
    {
        $locales = array_keys(config('app.locales'));
        $category = (string) ($tip['category'] ?? '');
        if (! array_key_exists($category, config('tips.categories'))) {
            throw new \InvalidArgumentException('Tip '.($index + 1).": unknown category \"$category\" (config/tips.php).");
        }

        $articles = [];
        foreach ($locales as $locale) {
            $a = $tip['articles'][$locale] ?? [];
            if (filled($a['title'] ?? null)) {
                $articles[$locale] = [
                    'title' => Str::limit(trim($a['title']), 255, ''),
                    'short_description' => filled($a['short'] ?? null) ? Str::limit(trim($a['short']), 500, '') : null,
                ];
            }
        }
        if (! $articles) {
            throw new \InvalidArgumentException('Tip '.($index + 1).': no title in any language.');
        }

        $slides = [];
        $links = [];
        foreach (array_values((array) ($tip['slides'] ?? [])) as $s) {
            $media = [];
            foreach (array_values((array) ($s['media'] ?? [])) as $m) {
                $file = $this->file(is_array($m) ? ($m['src'] ?? null) : $m);
                if ($file) {
                    $media[] = ['id' => 'm'.Str::random(8), 'src' => $file];
                }
            }

            $texts = [];
            foreach ($locales as $locale) {
                $t = (array) ($s['texts'][$locale] ?? []);
                $captions = [];
                foreach (array_values((array) ($t['captions'] ?? [])) as $i => $caption) {
                    if (isset($media[$i])) {
                        $captions[$media[$i]['id']] = $caption;
                    }
                }
                $texts[$locale] = [
                    'title' => $t['title'] ?? '',
                    'body' => $this->textImages((string) ($t['body'] ?? '')),
                    'note' => $this->textImages((string) ($t['note'] ?? '')),
                    'captions' => $captions,
                ];
            }

            $slides[] = [
                'id' => Str::random(10),
                'layout' => $s['layout'] ?? ($media ? 'picture_text' : 'text'),
                'light' => (bool) ($s['light'] ?? false),
                'media' => $media,
                'note' => $s['note'] ?? null,
                'texts' => $texts,
            ];
            $links[count($slides) - 1] = $s['link'] ?? null;
        }

        // Same checks as the editor; clean() drops empty slides, so the links are matched by the slide id.
        $clean = $this->slides->clean($slides);
        $linkById = [];
        foreach ($slides as $i => $s) {
            $linkById[$s['id']] = $links[$i];
        }

        return [
            'key' => isset($tip['key']) ? (string) $tip['key'] : null,
            'sort_order' => isset($tip['sort_order']) ? (int) $tip['sort_order'] : null,
            'tip' => [
                'category' => $category,
                'level' => in_array((int) ($tip['level'] ?? 0), array_keys(config('tips.levels')), true) ? (int) $tip['level'] : null,
                'show_warning' => (bool) ($tip['show_warning'] ?? false),
                'is_published' => (bool) ($tip['published'] ?? true),
                'image' => ($cover = $this->file($tip['cover'] ?? null)) ? TipMedia::DIR.'/'.$cover : null,
            ],
            'articles' => $articles,
            'slides' => $clean,
            'links' => array_map(fn ($s) => $linkById[$s['id']] ?? null, $clean),
        ];
    }

    /** ![caption](address "left") in a text → ![caption](media:<stored file> "left"); unusable pictures are removed. */
    private function textImages(string $text): string
    {
        return preg_replace_callback(self::TEXT_IMAGE, function ($m) {
            $file = $this->file($m[2]);

            return $file ? '!['.$m[1].'](media:'.$file.(isset($m[3]) ? ' "'.$m[3].'"' : '').')' : '';
        }, $text);
    }

    /** A picture / video address (link or file path) → stored file name, or null (with a warning) when it can't be used. */
    private function file(mixed $address): ?string
    {
        if (! is_string($address) || ($address = trim($address)) === '') {
            return null;
        }
        if (isset($this->stored[$address])) {
            return $this->stored[$address];
        }
        if ($existing = $this->media->pathOf($address)) { // already an uploaded file name
            return $this->stored[$address] = basename($existing);
        }

        $temp = null;
        if (preg_match('#^https?://#i', $address)) {
            $upload = $this->media->download($address, $error);
            $temp = $upload?->getRealPath();
        } else {
            $path = str_starts_with($address, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $address) ? $address : $this->baseDir.'/'.$address;
            $upload = is_file($path) ? new UploadedFile($path, basename($path), null, null, true) : null;
            $error = $upload ? null : 'file not found';
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($upload && ! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', ...TipMedia::VIDEO_EXTENSIONS], true)) {
                [$upload, $error] = [null, 'not a picture or video'];
            }
        }

        if (! $upload) {
            ($this->log)("  ! skipped $address: $error");

            return null;
        }

        try {
            $stored = basename($this->media->store($upload));
        } catch (\InvalidArgumentException $e) {
            ($this->log)("  ! skipped $address: {$e->getMessage()}");

            return null;
        } finally {
            if ($temp) {
                @unlink($temp);
            }
        }

        return $this->stored[$address] = $stored;
    }
}
