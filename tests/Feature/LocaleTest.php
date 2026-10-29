<?php

namespace Tests\Feature;

use App\Models\ClubMeeting;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_switcher_remembers_the_language(): void
    {
        $this->get('/')->assertOk()->assertSee('Gym rules')->assertSee('/locale/ka', false);
        $this->get('/locale/fr')->assertNotFound();
        $this->withUnencryptedCookie('locale', 'fr')->get('/')->assertSee('Gym rules'); // unknown cookie → default

        $this->from('/terms')->get('/locale/ka')->assertRedirect('/terms')->assertCookie('locale', 'ka', false);
        $this->withUnencryptedCookie('locale', 'ka')->get('/')->assertOk()
            ->assertSee('lang="ka"', false)->assertSee('დარბაზის წესები')->assertDontSee('Gym rules');

    }

    public function test_every_page_renders_in_georgian(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $member = ClubMember::factory()->create();
        $meeting = ClubMeeting::create(['title' => 'Monthly', 'starts_at' => now(), 'is_open' => true]);

        foreach (['/', '/terms', '/privacy', '/login', '/register', '/forgot-password', "/m/{$meeting->token}"] as $url) {
            $this->withUnencryptedCookie('locale', 'ka')->get($url)->assertOk()->assertSee('lang="ka"', false);
        }
        foreach ([
            '/admin', '/admin/entry', '/admin/visitors', '/admin/visits', '/admin/subscriptions', '/admin/pricing', '/admin/users',
            '/admin/roles', '/admin/activity', '/admin/club/members', "/admin/club/members/{$member->id}/edit", '/admin/club/import',
            '/admin/club/meetings', "/admin/club/meetings/{$meeting->id}", "/admin/club/meetings/{$meeting->id}/screen",
        ] as $url) {
            $this->actingAs($admin)->withUnencryptedCookie('locale', 'ka')->get($url)->assertOk();
        }
        $this->actingAs($admin)->withUnencryptedCookie('locale', 'ka')->get('/admin/club/members')->assertSee('კლუბის წევრები');
    }

    public function test_validation_messages_are_georgian(): void
    {
        $meeting = ClubMeeting::create(['title' => 'Monthly', 'starts_at' => now(), 'is_open' => true]);
        $this->withUnencryptedCookie('locale', 'ka')->get("/m/{$meeting->token}?c=".$meeting->checkinCode());

        $this->withUnencryptedCookie('locale', 'ka')->post("/m/{$meeting->token}", ['new' => 1, 'first_name' => 'ნინო'])
            ->assertSessionHasErrors(['last_name' => 'ველი „გვარი“ სავალდებულოა.']);
    }

    /** Every __('…') in the code and every t('…') in the Vue components has a Georgian translation in lang/ka.json. */
    public function test_every_text_has_a_georgian_translation(): void
    {
        $georgian = json_decode(file_get_contents(lang_path('ka.json')), true);
        $missing = [];

        $files = [];
        foreach (['app', 'resources/views', 'resources/js', 'config'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (preg_match('/\.(php|vue|js)$/', $file->getFilename())) {
                    $files[] = $file->getPathname();
                }
            }
        }
        foreach ($files as $path) {
            $pattern = str_ends_with($path, '.php')
                ? "/(?:__|trans_choice|@lang)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/"
                : "/\\bt\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/";
            preg_match_all($pattern, file_get_contents($path), $matches);
            foreach ($matches[1] as $key) {
                $key = stripslashes($key);
                // "auth.failed" style keys live in lang/ka/*.php
                if (! isset($georgian[$key]) && ! preg_match('/^[a-z_]+\.[a-z_]+$/', $key)) {
                    $missing[$key] = str_replace(base_path().'/', '', $path);
                }
            }
        }

        $this->assertSame([], $missing, 'Add these texts to lang/ka.json');
    }
}
