<?php

namespace Tests\Feature;

use App\Models\ClubMeeting;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ClubTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    public function test_admin_sees_every_club_page(): void
    {
        $member = ClubMember::factory()->create();
        $meeting = ClubMeeting::create(['title' => 'Monthly', 'starts_at' => now(), 'is_open' => true]);

        foreach ([
            '/admin/club/members', '/admin/club/members/create', "/admin/club/members/{$member->id}/edit", '/admin/club/import',
            '/admin/club/meetings', '/admin/club/meetings/create', "/admin/club/meetings/{$meeting->id}",
            "/admin/club/meetings/{$meeting->id}/edit", "/admin/club/meetings/{$meeting->id}/screen",
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->actingAs($this->admin)->get('/admin/club/members/export')->assertOk()->assertDownload();
        $this->actingAs($this->admin)->get('/admin/club/members/template')->assertOk()->assertDownload();
        $this->actingAs($this->admin)->get("/admin/club/meetings/{$meeting->id}/export")->assertOk()->assertDownload();
        $this->actingAs($this->admin)->getJson("/admin/club/meetings/{$meeting->id}/qr")->assertOk()->assertJsonStructure(['url', 'svg', 'count', 'latest']);
    }

    public function test_worker_has_no_club_access_by_default(): void
    {
        $staff = User::factory()->create()->assignRole('worker');

        $this->actingAs($staff)->get('/admin/club/members')->assertForbidden();
        $this->actingAs($staff)->get('/admin/club/meetings')->assertForbidden();
    }

    public function test_member_needs_id_number_or_phone(): void
    {
        $this->actingAs($this->admin)->post('/admin/club/members', ['first_name' => 'Nino', 'last_name' => 'Beridze', 'is_active' => 1])
            ->assertSessionHasErrors(['personal_id', 'phone']);

        $this->actingAs($this->admin)->post('/admin/club/members', ['first_name' => 'Nino', 'last_name' => 'Beridze', 'phone' => '599 12-34-56', 'is_active' => 1])
            ->assertRedirect('/admin/club/members');
        $this->assertDatabaseHas('club_members', ['first_name' => 'Nino', 'phone' => '599123456', 'personal_id' => null]);
    }

    public function test_excel_import_skips_members_that_are_already_added(): void
    {
        ClubMember::factory()->create(['first_name' => 'Giorgi', 'personal_id' => '01001012345', 'phone' => '599000001']);

        $file = $this->xlsx([
            ['სახელი', 'გვარი', 'პირადი ნომერი', 'ტელეფონი', 'დაბადების თარიღი'],
            ['Giorgi', 'Kapanadze', 1001012345, '599 99 99 99', '01.02.1990'], // ID lost its leading zero in Excel → duplicate
            ['Ana', 'Lomidze', '12345678901', '555123456', new \DateTimeImmutable('1995-05-05')],
            ['Ana', 'Lomidze', '', '555123456', ''],                            // same phone as the row above
            ['Luka', 'Tsiklauri', '', '', ''],                                  // no ID / phone
            ['Mariam Kvaratskhelia', '', '', '577111222', ''],                  // full name in one cell
        ]);

        $response = $this->actingAs($this->admin)->post('/admin/club/import/preview', ['file' => $file])->assertOk();
        $this->assertSame(['new' => 2, 'duplicate' => 2, 'invalid' => 1], $response->viewData('summary'));
        $this->assertDatabaseCount('club_members', 1); // nothing saved before confirming

        $this->actingAs($this->admin)->post('/admin/club/import', ['key' => $response->viewData('key')])->assertRedirect('/admin/club/members');

        $this->assertDatabaseCount('club_members', 3);
        $this->assertDatabaseHas('club_members', ['first_name' => 'Ana', 'personal_id' => '12345678901', 'birth_date' => '1995-05-05']);
        $this->assertDatabaseHas('club_members', ['first_name' => 'Mariam', 'last_name' => 'Kvaratskhelia', 'phone' => '577111222']);

        // The same key can't be imported twice.
        $this->actingAs($this->admin)->post('/admin/club/import', ['key' => $response->viewData('key')])->assertRedirect('/admin/club/import');
        $this->assertDatabaseCount('club_members', 3);
    }

    public function test_csv_with_semicolons_can_be_imported(): void
    {
        $file = UploadedFile::fake()->createWithContent('members.csv', "First name;Last name;Phone;Email\nNika;Gelashvili;599111222;nika@gmail.com\n");

        $response = $this->actingAs($this->admin)->post('/admin/club/import/preview', ['file' => $file])->assertOk();
        $this->actingAs($this->admin)->post('/admin/club/import', ['key' => $response->viewData('key')]);

        $this->assertDatabaseHas('club_members', ['first_name' => 'Nika', 'email' => 'nika@gmail.com']);
    }

    public function test_exported_file_can_be_imported_again_without_duplicates(): void
    {
        ClubMember::factory()->count(3)->create();

        $export = $this->actingAs($this->admin)->get('/admin/club/members/export');
        $file = new UploadedFile($export->getFile()->getPathname(), 'export.xlsx', null, null, true);

        $response = $this->actingAs($this->admin)->post('/admin/club/import/preview', ['file' => $file])->assertOk();
        $this->assertSame(['new' => 0, 'duplicate' => 3, 'invalid' => 0], $response->viewData('summary'));
    }

    public function test_member_finds_their_name_and_checks_in(): void
    {
        $member = ClubMember::factory()->create(['first_name' => 'Nino', 'last_name' => 'Beridze', 'phone' => '599123456', 'email' => 'nino@gmail.com']);
        ClubMember::factory()->create(['first_name' => 'Nino', 'last_name' => 'Kapanadze']);
        $meeting = ClubMeeting::create(['title' => 'Monthly', 'starts_at' => now(), 'is_open' => true]);
        $url = "/m/{$meeting->token}";

        // Opening the link without a fresh code from the screen is not enough.
        $this->get($url)->assertOk()->assertSee('scan the QR code');
        $this->post($url, ['member_id' => $member->id, 'q' => 'Nino Beridze']);
        $this->assertDatabaseCount('club_meeting_attendances', 0);

        $this->get("{$url}?c=wrong")->assertRedirect($url);
        $this->get($url)->assertSee('scan the QR code');

        // Scan → search by name (any order, start of the name is enough) → pick yourself.
        $this->get("{$url}?c=".$meeting->checkinCode())->assertRedirect($url);
        $this->get($url)->assertSee('Find me');
        $this->get("{$url}?q=beri+nin")
            ->assertOk()->assertSee('Nino Beridze')->assertSee('•••456')->assertDontSee('Kapanadze');
        $this->get("{$url}?q=Nino")->assertSee('Nino Beridze')->assertSee('Nino Kapanadze');

        // Phone, ID number and email find the member too (exact match only).
        $this->get("{$url}?q=599-123-456")->assertSee('Nino Beridze')->assertDontSee('Kapanadze');
        $this->get("{$url}?q={$member->personal_id}")->assertSee('Nino Beridze')->assertDontSee('Kapanadze');
        $this->get("{$url}?q=".strtoupper($member->email))->assertSee('Nino Beridze')->assertDontSee('Kapanadze');
        $this->get("{$url}?q=599123")->assertDontSee('Nino Beridze');

        // A member id that doesn't match the search is refused.
        $other = ClubMember::where('last_name', 'Kapanadze')->first();
        $this->post($url, ['member_id' => $other->id, 'q' => 'Nino Beridze'])->assertSessionHasErrors('q');
        $this->assertDatabaseCount('club_meeting_attendances', 0);

        $this->post($url, ['member_id' => $member->id, 'q' => 'Nino Beridze'])
            ->assertRedirect($url)
            ->assertCookie('club_member');
        $this->assertDatabaseHas('club_meeting_attendances', ['club_meeting_id' => $meeting->id, 'club_member_id' => $member->id, 'method' => 'qr']);

        // The phone remembers the member: one tap next time, and no double check-in.
        $this->withCookie('club_member', (string) $member->id)->get($url)->assertSee('checked in')->assertDontSee('Find me');
        $this->withCookie('club_member', (string) $member->id)->post($url, ['remembered' => 1]);
        $this->assertDatabaseCount('club_meeting_attendances', 1);
    }

    public function test_unknown_person_registers_and_admin_approves_or_merges(): void
    {
        $meeting = ClubMeeting::create(['title' => 'Monthly', 'starts_at' => now(), 'is_open' => true]);
        $url = "/m/{$meeting->token}";
        $this->get("{$url}?c=".$meeting->checkinCode());

        $this->get("{$url}?q=Luka+Tsiklauri")->assertOk()->assertSee('not registered yet')->assertSee('value="Tsiklauri"', false);
        $this->get("{$url}?q=luka@gmail.com")->assertSee('value="luka@gmail.com"', false);

        // Every field is required.
        $this->post($url, ['new' => 1, 'q' => 'Luka', 'first_name' => 'Luka', 'last_name' => 'Tsiklauri'])
            ->assertRedirect("{$url}?q=Luka")
            ->assertSessionHasErrors(['personal_id', 'phone', 'email']);
        $this->assertDatabaseCount('club_members', 0);

        $luka = ['new' => 1, 'first_name' => 'Luka', 'last_name' => 'Tsiklauri', 'personal_id' => '01001012345', 'phone' => '577 11 12 22', 'email' => 'Luka@Gmail.com'];
        $this->post($url, $luka)->assertRedirect($url)->assertSessionHasNoErrors();

        $luka = ClubMember::where('first_name', 'Luka')->firstOrFail();
        $this->assertTrue($luka->is_pending);
        $this->assertSame('577111222', $luka->phone);
        $this->assertSame('luka@gmail.com', $luka->email);
        $this->assertDatabaseHas('club_meeting_attendances', ['club_member_id' => $luka->id]);

        // ID number, phone and email can't be registered twice: the person is told to search instead.
        $this->post($url, ['new' => 1, 'first_name' => 'Luka', 'last_name' => 'Other', 'personal_id' => '01001012345', 'phone' => '577111222', 'email' => 'luka@gmail.com'])
            ->assertSessionHasErrors(['personal_id', 'phone', 'email']);
        $this->assertSame(1, ClubMember::count());

        // Admin sees them in the "Self-registered" tab, not in the main list.
        $this->actingAs($this->admin)->get('/admin/club/members?tab=new')->assertOk()->assertSee('Tsiklauri');
        $this->actingAs($this->admin)->get('/admin/club/members')->assertOk()->assertDontSee('Tsiklauri');
        $this->actingAs($this->admin)->get("/admin/club/members/{$luka->id}/edit")->assertOk()->assertSee('Add to the member list');

        // The admin form doesn't allow another member's phone or email either.
        $other = ClubMember::factory()->create();
        $this->actingAs($this->admin)->put("/admin/club/members/{$other->id}", ['first_name' => 'A', 'last_name' => 'B', 'phone' => '577111222', 'email' => 'luka@gmail.com'])
            ->assertSessionHasErrors(['phone', 'email']);

        $this->actingAs($this->admin)->post("/admin/club/members/{$luka->id}/approve")->assertRedirect();
        $this->assertFalse($luka->fresh()->is_pending);

        // Somebody who is already a member registers again → merged into the existing one.
        $nino = ClubMember::factory()->create(['first_name' => 'Nino', 'last_name' => 'Beridze', 'phone' => null, 'email' => null]);
        $this->post($url, ['new' => 1, 'first_name' => 'Nino', 'last_name' => 'Beridze', 'personal_id' => '01001099999', 'phone' => '599000111', 'email' => 'nino@gmail.com'])
            ->assertSessionHasNoErrors();
        $copy = ClubMember::where('is_pending', true)->firstOrFail();

        $this->actingAs($this->admin)->get('/admin/club/members?tab=new')->assertSee('Merge into Nino Beridze');
        $this->actingAs($this->admin)->post("/admin/club/members/{$copy->id}/merge", ['target_id' => $nino->id])->assertRedirect('/admin/club/members?tab=new');

        $this->assertModelMissing($copy);
        $this->assertDatabaseHas('club_meeting_attendances', ['club_meeting_id' => $meeting->id, 'club_member_id' => $nino->id]);
        $this->assertSame('599000111', $nino->fresh()->phone);
        $this->assertSame('nino@gmail.com', $nino->fresh()->email);
    }

    public function test_old_qr_codes_expire_and_closed_meetings_reject_check_in(): void
    {
        config(['club.qr_rotation_seconds' => 30, 'club.qr_grace_seconds' => 60]);
        $meeting = ClubMeeting::create(['title' => 'Monthly', 'starts_at' => now(), 'is_open' => true]);
        $window = ClubMeeting::currentWindow();

        $this->assertTrue($meeting->isValidCheckinCode($meeting->checkinCode($window - 2)));
        $this->assertFalse($meeting->isValidCheckinCode($meeting->checkinCode($window - 3)));

        ClubMember::factory()->create();
        $meeting->update(['is_open' => false]);
        $this->get("/m/{$meeting->token}?c=".$meeting->checkinCode());
        $this->get("/m/{$meeting->token}")->assertSee('closed');
        $this->post("/m/{$meeting->token}", ['new' => 1, 'first_name' => 'Nino', 'last_name' => 'Beridze', 'personal_id' => '01001012345', 'phone' => '599123456', 'email' => 'nino@gmail.com']);
        $this->assertDatabaseCount('club_meeting_attendances', 0);
        $this->assertDatabaseCount('club_members', 1);
    }

    public function test_admin_marks_attendance_manually(): void
    {
        $members = ClubMember::factory()->count(2)->create();
        $meeting = ClubMeeting::create(['title' => 'Monthly', 'starts_at' => now(), 'is_open' => true]);

        $this->actingAs($this->admin)->post("/admin/club/meetings/{$meeting->id}/attendances", ['member_ids' => $members->pluck('id')->all()])->assertRedirect();
        $this->assertDatabaseCount('club_meeting_attendances', 2);
        $this->assertDatabaseHas('club_meeting_attendances', ['club_member_id' => $members[0]->id, 'method' => 'manual']);

        $attendance = $meeting->attendances()->first();
        $this->actingAs($this->admin)->delete("/admin/club/meetings/{$meeting->id}/attendances/{$attendance->id}")->assertRedirect();
        $this->assertDatabaseCount('club_meeting_attendances', 1);
    }

    public function test_finished_meeting_is_closed_for_good(): void
    {
        $member = ClubMember::factory()->create();
        $meeting = ClubMeeting::create(['title' => 'Monthly', 'starts_at' => now(), 'is_open' => true]);
        $this->get("/m/{$meeting->token}?c=".$meeting->checkinCode());

        $this->actingAs($this->admin)->post("/admin/club/meetings/{$meeting->id}/close")->assertRedirect();
        $this->assertFalse($meeting->fresh()->is_open);

        // Editing doesn't reopen it.
        $this->actingAs($this->admin)->put("/admin/club/meetings/{$meeting->id}", ['title' => 'Renamed', 'starts_at' => now()->format('Y-m-d H:i'), 'is_open' => 1]);
        $this->assertFalse($meeting->fresh()->is_open);

        // Nobody can be added: neither by the admin nor through the QR page.
        $this->actingAs($this->admin)->post("/admin/club/meetings/{$meeting->id}/attendances", ['member_ids' => [$member->id]])->assertSessionHasErrors('member_ids');
        $this->withCookie('club_member', (string) $member->id)->post("/m/{$meeting->token}", ['remembered' => 1]);
        $this->assertDatabaseCount('club_meeting_attendances', 0);

        // The screen shows "closed" instead of the QR code.
        $this->actingAs($this->admin)->get("/admin/club/meetings/{$meeting->id}/screen")->assertOk()->assertSee('Registration is closed')->assertDontSee('<svg', false);
        $this->actingAs($this->admin)->getJson("/admin/club/meetings/{$meeting->id}/qr")->assertJson(['is_open' => false, 'svg' => null, 'url' => null]);
        $this->actingAs($this->admin)->get("/admin/club/meetings/{$meeting->id}")->assertSee('This meeting is finished')->assertDontSee('Finish meeting');
    }

    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'members.xlsx', null, null, true);
    }
}
