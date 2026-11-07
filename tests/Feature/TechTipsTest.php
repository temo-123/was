<?php

namespace Tests\Feature;

use App\Http\Controllers\Tips\KioskController;
use App\Models\TechTip;
use App\Models\TechTipArticle;
use App\Models\User;
use App\Services\Tips\TipMedia;
use Database\Seeders\TechTipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechTipsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['tips.port' => 8090]);
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    /** A published tip with one text slide, in the given languages. */
    private function tip(array $attributes = [], array $titles = ['ka' => 'ხელები გაშლილი', 'en' => 'Straight arms'], bool $withSlide = true): TechTip
    {
        $tip = TechTip::create($attributes + ['category' => 'technique', 'is_published' => true]);
        foreach ($titles as $locale => $title) {
            $tip->articles()->create(['locale' => $locale, 'title' => $title, 'short_description' => "$title short"]);
        }
        if ($withSlide) {
            $slide = $tip->slides()->create(['position' => 0, 'layout' => 'text', 'media' => []]);
            foreach ($titles as $locale => $title) {
                $slide->texts()->create(['locale' => $locale, 'title' => "$title 1", 'body' => "Hang on **straight** arms.\n\n<script>alert(1)</script>", 'captions' => []]);
            }
        }

        return $tip;
    }

    /** Uploads a file the way the editor does; returns its name. */
    private function upload(?UploadedFile $file = null, string $kind = 'image'): string
    {
        return $this->actingAs($this->admin)
            ->postJson('/admin/tech-tips/upload', ['file' => $file ?? UploadedFile::fake()->image('p.jpg', 3000, 2000), 'kind' => $kind])
            ->assertOk()->json('src');
    }

    private function save(array $data, ?TechTip $tip = null)
    {
        $data += ['category' => 'technique', 'is_published' => 1];
        $data['slides'] = json_encode($data['slides'] ?? []);

        return $tip
            ? $this->actingAs($this->admin)->put("/admin/tech-tips/{$tip->id}", $data)
            : $this->actingAs($this->admin)->post('/admin/tech-tips', $data);
    }

    private function textSlide(string $title, string $locale = 'en'): array
    {
        return ['layout' => 'text', 'texts' => [$locale => ['title' => $title, 'body' => 'Text']]];
    }

    public function test_tablet_page_shows_only_published_tips_with_slides(): void
    {
        $this->tip();
        $this->tip(['is_published' => false], ['en' => 'Secret draft']);
        $this->tip([], ['en' => 'No slides yet'], withSlide: false);

        $page = $this->get('/tips')->assertOk()->assertSee('data-vue="TechTips"', false);
        $page->assertSee('Straight arms')->assertSee('ხელები გაშლილი')->assertDontSee('Secret draft')->assertDontSee('No slides yet');

        $data = $this->getJson('/tips/data')->assertOk()->json('tips');
        $this->assertCount(1, $data);
        $slide = $data[0]['slides'][0];
        $this->assertSame('text', $slide['layout']);
        $this->assertSame('Straight arms 1', $slide['texts']['en']['title']);
        $this->assertStringContainsString('<strong>straight</strong>', $slide['texts']['en']['html']);
        $this->assertStringNotContainsString('<script>', $slide['texts']['en']['html']); // raw HTML is dropped
        $this->assertTrue($data[0]['warning']);
    }

    public function test_the_tablet_port_only_reaches_the_tips(): void
    {
        $this->get('http://localhost:8090/')->assertRedirect(route('tips.index'));
        $this->get('http://localhost:8090/admin')->assertRedirect(route('tips.index'));
        $this->get('http://localhost:8090/login')->assertRedirect(route('tips.index'));
        $this->post('http://localhost:8090/login', ['email' => 'a@b.c'])->assertNotFound();
        $this->get('http://localhost:8090/tips')->assertOk();

        // The normal address works as before.
        $this->get('http://localhost/login')->assertOk();
    }

    public function test_reading_a_tip_counts_without_a_csrf_token(): void
    {
        $tip = $this->tip();
        $hidden = $this->tip(['is_published' => false]);

        $this->post("/tips/{$tip->id}/viewed")->assertNoContent();
        $this->post("/tips/{$tip->id}/viewed")->assertNoContent();
        $this->post("/tips/{$hidden->id}/viewed")->assertNotFound();
        $this->assertSame(2, $tip->fresh()->views);
    }

    public function test_uploads_shrink_pictures_and_keep_videos(): void
    {
        $picture = $this->upload();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}\.webp$/', $picture);
        $this->assertSame(1600, getimagesizefromstring(Storage::disk('local')->get("tech-tips/$picture"))[0]);
        $this->get("/tips/media/$picture")->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

        $video = $this->upload(UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'), 'video');
        $this->assertStringEndsWith('.mp4', $video);

        $this->actingAs($this->admin)->postJson('/admin/tech-tips/upload', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertUnprocessable();
        $this->get('/tips/media/../../.env')->assertNotFound();
    }

    public function test_a_picture_can_come_from_a_link(): void
    {
        ob_start();
        imagepng(imagecreatetruecolor(2000, 1000));
        $png = ob_get_clean();
        Http::fake([
            '93.184.215.14/p.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            '93.184.215.14/page' => Http::response('<html>not a picture</html>', 200, ['Content-Type' => 'text/html']),
            '*' => Http::response('', 404),
        ]);

        $src = $this->actingAs($this->admin)->postJson('/admin/tech-tips/upload', ['url' => 'http://93.184.215.14/p.png'])
            ->assertOk()->json('src');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}\.(webp|jpg)$/', $src);
        $this->assertSame(1600, getimagesizefromstring(Storage::disk('local')->get("tech-tips/$src"))[0]);

        foreach (['http://93.184.215.14/page', 'http://93.184.215.14/missing.png', 'http://127.0.0.1/p.png',
            'http://192.168.1.5/p.png', 'file:///etc/passwd', 'not a link'] as $url) {
            $this->actingAs($this->admin)->postJson('/admin/tech-tips/upload', ['url' => $url])
                ->assertUnprocessable()->assertJsonValidationErrors('url');
        }
        Http::assertSentCount(3); // private addresses are never requested
        Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'Mozilla')); // CDNs refuse clients without one
    }

    public function test_the_seeder_replaces_all_tips(): void
    {
        $old = $this->tip(['image' => 'tech-tips/'.$this->upload()]);
        $oldFile = $old->image;
        $this->seed(TechTipSeeder::class);

        $this->assertModelMissing($old);
        $this->assertFalse(Storage::disk('local')->exists($oldFile)); // old pictures are deleted too
        $this->assertSame(0, TechTipArticle::whereNotIn('tech_tip_id', TechTip::pluck('id'))->count());
        $this->assertSame(count(json_decode(file_get_contents(database_path('seeders/data/tech-tips.json')), true)), TechTip::count());
        $this->assertSame(0, TechTip::doesntHave('slides')->count());
        $this->assertSame(0, TechTipArticle::where('locale', 'ka')->count() - TechTip::count()); // every tip in Georgian
        $this->assertNotNull(TechTip::whereHas('slides', fn ($q) => $q->whereNotNull('link_tip_id'))->first()); // "link": "key" resolved
        $this->get('/tips')->assertOk();
    }

    public function test_the_seeder_takes_pictures_from_the_tip_folders(): void
    {
        $folder = database_path('seeders/data/images/figure-eight');
        $made = [];
        foreach (['cover.png', '1.png', '1-2.png', '2.svg', 'notes.png'] as $name) {
            $made[] = $file = "$folder/$name";
            if (str_ends_with($name, '.svg')) {
                file_put_contents($file, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="4" height="4"/></svg>');
            } else {
                imagepng(imagecreatetruecolor(40, 30), $file);
            }
        }

        try {
            $this->seed(TechTipSeeder::class);
        } finally {
            array_map('unlink', $made);
        }

        $tip = TechTip::whereHas('articles', fn ($q) => $q->where('title', 'like', 'Tying in%'))->with('slides')->sole();
        $this->assertNotNull($tip->image);
        $this->assertSame('sequence', $tip->slides[0]->layout); // 1.png + 1-2.png
        $this->assertCount(2, $tip->slides[0]->media);
        $this->assertSame('picture_text', $tip->slides[1]->layout);
        $this->assertStringEndsWith('.svg', $tip->slides[1]->media[0]['src']);
        $this->assertCount(4, Storage::disk('local')->files('tech-tips')); // notes.png is not a slide number: ignored
    }

    public function test_the_import_command_downloads_pictures(): void
    {
        ob_start();
        imagepng(imagecreatetruecolor(400, 300));
        $png = ob_get_clean();
        Http::fake(['93.184.215.14/*' => fn () => Http::response($png, 200)]); // a fresh body for every download
        $file = tempnam(sys_get_temp_dir(), 'tips').'.json';
        file_put_contents($file, json_encode([[
            'key' => 'a', 'category' => 'ice', 'cover' => 'http://93.184.215.14/cover.png',
            'articles' => ['en' => ['title' => 'Screws', 'short' => 'x']],
            'slides' => [
                ['layout' => 'picture', 'media' => ['http://93.184.215.14/1.png'], 'link' => 'a',
                    'texts' => ['en' => ['title' => 'One', 'body' => "Text\n\n![pic](http://93.184.215.14/2.png \"left\")", 'captions' => ['First']]]],
            ],
        ]]));

        $this->artisan('tips:import', ['file' => $file])->assertSuccessful();
        @unlink($file);

        $tip = TechTip::with('slides.texts')->sole();
        $this->assertNotNull($tip->image);
        $slide = $tip->slides->first();
        $this->assertCount(1, $slide->media);
        $this->assertSame(['First'], array_values($slide->texts->first()->captions));
        $this->assertMatchesRegularExpression('/media:[A-Za-z0-9]{32}\.\w+ "left"/', $slide->texts->first()->body);
        $this->assertNull($slide->link_tip_id); // a tip doesn't link to itself
        $this->assertCount(3, Storage::disk('local')->files('tech-tips'));
    }

    public function test_svg_drawings_are_cleaned_and_served_safely(): void
    {
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script>'
            .'<rect width="10" height="10" fill="red"/><image href="http://evil.test/x.png"/></svg>';
        Http::fake(['93.184.215.14/*' => fn () => Http::response($svg, 200, ['Content-Type' => 'image/svg+xml'])]);

        $src = $this->actingAs($this->admin)->postJson('/admin/tech-tips/upload', ['url' => 'http://93.184.215.14/download/068w0000002tuNGAAY'])
            ->assertOk()->json('src');
        $this->assertStringEndsWith('.svg', $src);
        $stored = Storage::disk('local')->get("tech-tips/$src");
        $this->assertStringContainsString('<rect', $stored);
        foreach (['onload', '<script', 'evil.test'] as $bad) {
            $this->assertStringNotContainsString($bad, $stored);
        }
        $this->get("/tips/media/$src")->assertOk()
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:");
    }

    public function test_admin_builds_a_slide_show(): void
    {
        $other = $this->tip();
        [$cover, $a, $b, $c] = [$this->upload(), $this->upload(), $this->upload(), $this->upload()];
        $video = $this->upload(UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'), 'video');

        $this->actingAs($this->admin)->get('/admin/tech-tips')->assertOk()->assertSee(':8090');
        $this->actingAs($this->admin)->get('/admin/tech-tips/create')->assertOk()->assertSee('data-vue="TipEditor"', false);

        $slides = [
            // 1. picture + text, captions per language, a "more info" link, a note box
            ['id' => 'one', 'layout' => 'picture_text', 'light' => true, 'media' => [['id' => 'm1', 'src' => $a], ['id' => 'm2', 'src' => $b]],
                'note' => 'danger', 'link' => $other->id, 'texts' => [
                    'ka' => ['title' => 'შემოწმება', 'body' => 'კვანძი', 'note' => 'არასოდეს', 'captions' => ['m1' => 'კვანძი', 'm2' => 'not shown']],
                    'en' => ['title' => 'Check', 'body' => 'The **knot**', 'note' => 'Never', 'captions' => ['m1' => 'Knot']],
                ]],
            // 2. sequence with a made-up file
            ['layout' => 'sequence', 'media' => [['id' => 's1', 'src' => $a], ['id' => 's2', 'src' => 'nope.jpg'], ['id' => 's3', 'src' => $b]],
                'texts' => ['ka' => ['title' => 'მიმდევრობა']]],
            // 3. compare: first = correct, second = wrong
            ['layout' => 'compare', 'media' => [['src' => $b, 'mark' => 'bad'], ['src' => $c]], 'texts' => ['ka' => ['title' => 'სწორი']]],
            // 4. text layout keeps no pictures; note text without a note colour is dropped
            ['layout' => 'text', 'media' => [['src' => $c]], 'note' => null, 'texts' => ['ka' => ['body' => 'მხოლოდ ტექსტი', 'note' => 'lost']]],
            // 5. video layout takes only videos
            ['layout' => 'video', 'media' => [['src' => $a], ['id' => 'v', 'src' => $video]], 'texts' => []],
            // dropped: empty / unknown layout with nothing in it
            ['layout' => 'text', 'texts' => ['ka' => ['title' => '  ']]],
            ['layout' => 'script', 'media' => [['src' => '../../.env']]],
        ];

        $this->save([
            'image' => $cover, 'level' => 2, 'show_warning' => 0, 'category' => 'belaying',
            'articles' => ['ka' => ['title' => 'დაზღვევა', 'short_description' => 'მოკლედ'], 'en' => ['title' => '']],
            'slides' => $slides,
        ])->assertRedirect('/admin/tech-tips')->assertSessionHasNoErrors();

        $tip = TechTip::with('articles', 'slides.texts')->latest('id')->first();
        $this->assertSame(['belaying', 2, false, "tech-tips/$cover"], [$tip->category, $tip->level, $tip->show_warning, $tip->image]);
        $this->assertSame(['ka'], $tip->articles->pluck('locale')->all());
        $this->assertSame(['picture_text', 'sequence', 'compare', 'text', 'video'], $tip->slides->pluck('layout')->all());

        [$one, $sequence, $compare, $text, $videoSlide] = $tip->slides;
        $this->assertSame([['id' => 'm1', 'src' => "tech-tips/$a", 'mark' => null]], $one->media); // picture + text: one picture
        $this->assertSame(['danger', $other->id, true], [$one->note_variant, $one->link_tip_id, $one->light]);
        $this->assertSame(['m1' => 'კვანძი'], $one->texts->firstWhere('locale', 'ka')->captions);
        $this->assertSame(['s1', 's3'], array_column($sequence->media, 'id'));
        $this->assertSame(['good', 'bad'], array_column($compare->media, 'mark'));
        $this->assertSame([], $text->media);
        $this->assertNull($text->texts->first()->note);
        $this->assertSame(["tech-tips/$video"], array_column($videoSlide->media, 'src'));

        $shown = collect($this->getJson('/tips/data')->json('tips'))->firstWhere('id', $tip->id);
        $this->assertStringContainsString("/tips/media/$cover", $shown['image']);
        $this->assertStringContainsString("/tips/media/$a", $shown['slides'][0]['media'][0]['src']);
        $this->assertStringContainsString('<strong>knot</strong>', $shown['slides'][0]['texts']['en']['html']);
        $this->assertSame('Never', strip_tags(trim($shown['slides'][0]['texts']['en']['note'])));
        $this->assertTrue($shown['slides'][4]['media'][0]['video']);

        $this->actingAs($this->admin)->get("/admin/tech-tips/{$tip->id}/edit")->assertOk()->assertSee('დაზღვევა')->assertSee('შემოწმება');
    }

    public function test_pictures_nobody_uses_are_deleted(): void
    {
        [$cover, $a, $b] = [$this->upload(), $this->upload(), $this->upload()];
        $slide = fn (...$files) => ['layout' => 'sequence', 'media' => array_map(fn ($f) => ['src' => $f], $files), 'texts' => []];

        $this->save(['image' => $cover, 'articles' => ['en' => ['title' => 'A']], 'slides' => [$slide($a, $b)]])->assertSessionHasNoErrors();
        $tip = TechTip::sole();
        // Another tip uses $a too.
        $this->save(['articles' => ['en' => ['title' => 'B']], 'slides' => [$slide($a)]])->assertSessionHasNoErrors();

        $this->save(['image' => $cover, 'articles' => ['en' => ['title' => 'A']], 'slides' => [$this->textSlide('only text')]], $tip)->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing("tech-tips/$b");
        Storage::disk('local')->assertExists("tech-tips/$a"); // still used by the other tip
        Storage::disk('local')->assertExists("tech-tips/$cover");

        $this->actingAs($this->admin)->delete("/admin/tech-tips/{$tip->id}")->assertRedirect('/admin/tech-tips');
        Storage::disk('local')->assertMissing("tech-tips/$cover");
        Storage::disk('local')->assertExists("tech-tips/$a");
        $this->assertDatabaseCount('tech_tip_slides', 1);
    }

    public function test_pictures_inside_a_text(): void
    {
        $picture = $this->upload();
        $body = "Intro\n\n![Hands close](media:$picture \"left\")\nNext to the picture.\n\n![lost](media:".str_repeat('B', 32).'.webp)';

        $this->save(['articles' => ['en' => ['title' => 'Inline']], 'slides' => [['layout' => 'text', 'texts' => ['en' => ['body' => $body]]]]])
            ->assertSessionHasNoErrors();

        $html = $this->getJson('/tips/data')->json('tips.0.slides.0.texts.en.html');
        $this->assertStringContainsString('<figure class="tt-inline-pic left"><img src="'.route('tips.media', $picture).'" alt="Hands close">', $html);
        $this->assertStringContainsString('<figcaption>Hands close</figcaption>', $html);
        $this->assertStringNotContainsString('BBBB', $html); // not an uploaded file

        app(TipMedia::class)->sweep(["tech-tips/$picture"]);
        Storage::disk('local')->assertExists("tech-tips/$picture"); // still used by the text
    }

    public function test_validation(): void
    {
        $this->save(['articles' => ['en' => ['title' => '']], 'slides' => [$this->textSlide('x')]])->assertSessionHasErrors('articles');
        $this->save(['articles' => ['en' => ['title' => 'No slides']], 'slides' => [['layout' => 'text', 'texts' => []]]])->assertSessionHasErrors('slides');
        $this->save(['articles' => ['ka' => ['title' => 'კი'], 'en' => ['title' => '', 'short_description' => 'No title']], 'slides' => [$this->textSlide('x')]])
            ->assertSessionHasErrors('articles.en.title');
        $this->save(['category' => 'nope', 'articles' => ['en' => ['title' => 'X']], 'slides' => [$this->textSlide('x')]])->assertSessionHasErrors('category');
        $this->actingAs($this->admin)->post('/admin/tech-tips', ['category' => 'technique', 'articles' => ['en' => ['title' => 'X']], 'slides' => '{broken'])
            ->assertSessionHasErrors('slides');
        $this->assertSame(0, TechTip::count());
    }

    public function test_preview_shows_the_unsaved_tip_on_the_tablet_page(): void
    {
        $tip = $this->tip();

        $this->actingAs($this->admin)->put("/admin/tech-tips/preview?tip={$tip->id}", [
            'category' => 'safety', 'preview_slide' => 1,
            'articles' => ['en' => ['title' => 'Changed but not saved']],
            'slides' => json_encode([$this->textSlide('First'), $this->textSlide('Second')]),
        ])->assertOk()->assertSee('data-vue="TechTips"', false)->assertSee('Changed but not saved')
            ->assertSee('"preview":true', false)->assertSee('"openSlide":1', false);

        $this->assertSame('Straight arms', $tip->fresh()->article('en')->title);
    }

    public function test_publish_toggle(): void
    {
        $tip = $this->tip();

        $this->actingAs($this->admin)->post("/admin/tech-tips/{$tip->id}/toggle")->assertRedirect();
        $this->assertFalse($tip->fresh()->is_published);
    }

    public function test_workers_and_guests_cannot_edit_tips(): void
    {
        $worker = User::factory()->create()->assignRole('worker');

        $this->actingAs($worker)->get('/admin/tech-tips')->assertForbidden();
        $this->actingAs($worker)->post('/admin/tech-tips', [])->assertForbidden();
        $this->actingAs($worker)->post('/admin/tech-tips/upload', [])->assertForbidden();
        auth()->logout();
        $this->get('/admin/tech-tips')->assertRedirect('/login');
    }

    /** The tablet switches language without the server, so every text of its Vue components is sent in KioskController::UI_TEXTS. */
    public function test_every_tablet_text_is_sent_to_the_tablet(): void
    {
        $keys = [];
        foreach (['TechTips.vue', 'tips/TipSlide.vue'] as $file) {
            preg_match_all('/\bt\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/', file_get_contents(resource_path("js/components/$file")), $matches);
            array_push($keys, ...array_filter($matches[1]), ...array_filter($matches[2]));
        }

        $this->assertNotEmpty($keys);
        $this->assertSame([], array_values(array_diff(array_unique($keys), KioskController::UI_TEXTS)));
    }
}
