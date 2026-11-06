<?php

namespace Tests\Feature;

use App\Http\Controllers\Tips\KioskController;
use App\Models\TechTip;
use App\Models\User;
use App\Services\Tips\TipMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
