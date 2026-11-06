<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Visits as a month calendar: visitor page, the climber's profile, the visits page. */
class VisitCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Visitor $visitor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->visitor = Visitor::factory()->create();

        foreach (['2026-09-28 10:00', '2026-10-05 18:30', '2026-10-05 20:10', '2026-10-14 09:15'] as $i => $at) {
            VisitorVisit::create(['visitor_id' => $this->visitor->id, 'visit_number' => $i + 1, 'entered_at' => $at, 'age_at_visit' => 30,
                'price' => 20, 'total_price' => 20, 'payment_method' => 'cash']);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_visitor_page_shows_the_month_of_the_last_visit(): void
    {
        $page = $this->actingAs($this->admin)->get("/admin/visitors/{$this->visitor->id}")->assertOk();

        $page->assertSee('October 2026')->assertSee('vcal-chip paid', false)->assertSee('18:30')->assertSee('20:10')->assertSee('09:15');
        $page->assertSee('3 visits');                    // in October
        $page->assertSee('60.00 ₾');                     // spent in October
        $page->assertSee('month=2026-09', false);         // the previous month is a link
        $page->assertSee('vcal-day is-other', false);     // 28–30 September fill the first week, greyed out
    }

    public function test_another_month_a_chosen_day_and_the_list(): void
    {
        $this->actingAs($this->admin)->get("/admin/visitors/{$this->visitor->id}?month=2026-09")->assertOk()
            ->assertSee('September 2026')->assertSee('1 visit');

        $this->actingAs($this->admin)->get("/admin/visitors/{$this->visitor->id}?month=2026-10&day=2026-10-05")->assertOk()
            ->assertSee('is-selected', false)->assertSee('05.10.2026 18:30')->assertSee('05.10.2026 20:10');

        // Bad values are ignored, not an error.
        $this->actingAs($this->admin)->get("/admin/visitors/{$this->visitor->id}?month=nonsense&day=2026-99-99")->assertOk();

        $this->actingAs($this->admin)->get("/admin/visitors/{$this->visitor->id}?view=list")->assertOk()
            ->assertSee('14.10.2026 09:15')->assertDontSee('vcal-grid', false);
    }

    public function test_the_climber_sees_their_own_calendar(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        \App\Models\VisitorUser::create(['visitor_id' => $this->visitor->id, 'user_id' => $user->id, 'linked_via' => 'manual']);

        $this->actingAs($user)->get('/profile?month=2026-10&day=2026-10-14')->assertOk()
            ->assertSee('My visits')->assertSee('vcal-grid', false)->assertSee('14.10.2026 09:15');
    }

    public function test_visits_page_calendar_counts_per_day(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/visits?view=calendar&month=2026-10')->assertOk();

        $page->assertSee('vcal-sum', false)->assertSee('October 2026');
        // 5 October: 2 visits, 40 ₾; the day links to the list of that day.
        $page->assertSee('from=2026-10-05&amp;to=2026-10-05', false)->assertSee('40 ₾');

        $this->actingAs($this->admin)->get('/admin/visits?from=2026-10-05&to=2026-10-05')->assertOk()->assertSee('20:10');
    }
}
