<?php

namespace Tests\Feature;

use App\Models\ClientTier;
use App\Models\RentalItem;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use App\Models\VisitReward;
use App\Models\VisitRental;
use Database\Seeders\PricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Rental gear, client statuses (VIP…) and every-N-th-visit rewards on top of the entry price. */
class RentalLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $admin;

    private RentalItem $shoes;

    private RentalItem $chalk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSeeder::class);
        $this->staff = User::factory()->create()->assignRole('worker');
        $this->admin = User::factory()->create()->assignRole('admin');
        Carbon::setTestNow('2026-10-20 19:15:00'); // Tuesday evening: adult single entry = 20 ₾

        $this->shoes = RentalItem::create(['name' => 'Climbing shoes', 'name_ka' => 'ფეხსაცმელი', 'price' => 5, 'has_sizes' => true, 'is_returnable' => true, 'stock' => 2]);
        $this->chalk = RentalItem::create(['name' => 'Chalk', 'price' => 2, 'is_returnable' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function single(): int
    {
        return TicketType::where('code', 'single')->value('id');
    }

    private function adult(array $attributes = []): Visitor
    {
        return Visitor::factory()->create($attributes + ['birth_date' => '1990-01-01', 'terms_accepted' => true]);
    }

    private function enter(Visitor $visitor, array $extra = [])
    {
        Carbon::setTestNow(now()->addMinutes(2)); // past the double-click guard

        return $this->actingAs($this->staff)->postJson('/api/v1/visits', $extra + ['visitor_id' => $visitor->id, 'ticket_type_id' => $this->single(), 'payment_method' => 'cash']);
    }

    public function test_rental_gear_is_added_to_the_price_and_shown_with_the_visit(): void
    {
        $visitor = $this->adult();

        $this->enter($visitor, ['rentals' => [
            ['rental_item_id' => $this->shoes->id, 'size' => ' 42 '],
            ['rental_item_id' => $this->chalk->id, 'quantity' => 2],
        ]])->assertCreated()
            ->assertJsonPath('data.price', 20)
            ->assertJsonPath('data.rental_price', 9)
            ->assertJsonPath('data.total_price', 29)
            ->assertJsonPath('data.rentals.0.size', '42')
            ->assertJsonPath('data.visit_number', 1);

        $visit = VisitorVisit::sole();
        $this->assertSame(['Climbing shoes', 'Chalk'], $visit->rentals->pluck('name')->all());

        // Shown on the visits list, the visitor page and the "rented now" list (shoes only: chalk doesn't come back).
        $this->actingAs($this->admin)->get('/admin/visits?from=2026-10-20&to=2026-10-20')->assertOk()->assertSee('29.00 ₾')->assertSee('Climbing shoes 42');
        $this->actingAs($this->admin)->get("/admin/visitors/{$visitor->id}")->assertOk()->assertSee('Climbing shoes');
        $this->actingAs($this->staff)->get('/admin/rentals')->assertOk()->assertSee($visitor->full_name);
        $this->assertSame(1, VisitRental::out()->count());

        $this->actingAs($this->staff)->post('/admin/rentals/'.$visit->rentals->first()->id.'/returned')->assertRedirect();
        $this->assertSame(0, VisitRental::out()->count());
    }

    public function test_quote_shows_the_whole_bill(): void
    {
        $visitor = $this->adult();
        $this->shoes->rentals()->create(['visitor_visit_id' => VisitorVisit::create(['visitor_id' => $visitor->id, 'entered_at' => now(), 'age_at_visit' => 36])->id,
            'name' => 'Climbing shoes', 'unit_price' => 5, 'price' => 5, 'is_returnable' => true]);

        $this->actingAs($this->staff)->getJson('/api/v1/pricing/quote?'.http_build_query([
            'visitor_id' => $visitor->id, 'ticket_type_id' => $this->single(), 'rentals' => [['rental_item_id' => $this->shoes->id]],
        ]))->assertOk()
            ->assertJsonPath('bill.entry', 20)
            ->assertJsonPath('bill.rental_total', 5)
            ->assertJsonPath('bill.total', 25)
            ->assertJsonPath('bill.visit_number', 2)
            ->assertJsonPath("gear_out.{$this->shoes->id}", 1);
    }

    public function test_subscription_covers_the_entry_but_gear_is_paid(): void
    {
        $visitor = $this->adult();
        $this->enter($visitor, ['ticket_type_id' => TicketType::where('code', 'monthly')->value('id')])->assertCreated();

        $this->enter($visitor, ['rentals' => [['rental_item_id' => $this->shoes->id]], 'payment_method' => 'card'])->assertCreated()
            ->assertJsonPath('data.price', 0)
            ->assertJsonPath('data.total_price', 5)
            ->assertJsonPath('data.payment_method', 'card');

        $visit = VisitorVisit::latest('id')->first();
        $this->assertNotNull($visit->visitor_subscription_id);
        $this->assertSame(2, $visit->subscription->visits_used);

        // Cancelling gives the entry back to the subscription.
        $this->actingAs($this->admin)->delete("/admin/visits/{$visit->id}")->assertRedirect();
        $this->assertSame(1, $visit->subscription->fresh()->visits_used);
    }

    public function test_client_status_discount_on_entry_and_gear(): void
    {
        $vip = ClientTier::create(['name' => 'VIP', 'discount_percent' => 50, 'rental_discount_percent' => 20, 'color' => '#ffcc00']);
        $gold = ClientTier::create(['name' => 'Gold', 'discount_percent' => 100]);

        $this->enter($this->adult(['client_tier_id' => $vip->id]), ['rentals' => [['rental_item_id' => $this->shoes->id]]])->assertCreated()
            ->assertJsonPath('data.price', 10)          // 20 − 50 %
            ->assertJsonPath('data.rental_price', 4)    // 5 − 20 %
            ->assertJsonPath('data.total_price', 14)
            ->assertJsonPath('data.discount_percent', 50)
            ->assertJsonPath('data.discount_amount', 10);

        $this->enter($this->adult(['client_tier_id' => $gold->id]))->assertCreated()
            ->assertJsonPath('data.total_price', 0)
            ->assertJsonPath('data.payment_method', 'free');

        // Expired or switched-off status: full price.
        $this->enter($this->adult(['client_tier_id' => $vip->id, 'client_tier_until' => '2026-10-19']))->assertJsonPath('data.price', 20);
        $gold->update(['is_active' => false]);
        $this->enter($this->adult(['client_tier_id' => $gold->id]))->assertJsonPath('data.price', 20);
    }

    public function test_every_nth_visit_reward(): void
    {
        VisitReward::create(['name' => 'Every 3rd free', 'every_visits' => 3, 'type' => 'free_entry']);
        VisitReward::create(['name' => 'Shoes every 2nd', 'every_visits' => 2, 'type' => 'free_rental', 'rental_item_id' => $this->shoes->id]);
        $visitor = $this->adult();
        $shoes = ['rentals' => [['rental_item_id' => $this->shoes->id, 'quantity' => 2]]];

        $this->enter($visitor, $shoes)->assertJsonPath('data.total_price', 30); // 1st: 20 + 2×5
        $this->enter($visitor, $shoes)->assertJsonPath('data.total_price', 25)  // 2nd: one pair free
            ->assertJsonPath('data.rewards.0.name', 'Shoes every 2nd')
            ->assertJsonPath('data.rentals.0.is_reward', true);
        $this->enter($visitor)->assertJsonPath('data.price', 0)                 // 3rd: free entry
            ->assertJsonPath('data.visit_number', 3)
            ->assertJsonPath('data.payment_method', 'free');
        $this->enter($visitor, $shoes + ['apply_rewards' => false])->assertJsonPath('data.total_price', 30); // 4th: staff didn't give it

        // The visitor page counts down to the next reward.
        $this->actingAs($this->admin)->get("/admin/visitors/{$visitor->id}")->assertOk()->assertSee('Next reward')->assertSee('Free entry');
    }

    public function test_status_discount_and_reward_stack(): void
    {
        $tier = ClientTier::create(['name' => 'VIP', 'discount_percent' => 50]);
        VisitReward::create(['name' => '−20 % every 2nd', 'every_visits' => 2, 'type' => 'discount', 'value' => 20]);
        $visitor = $this->adult(['client_tier_id' => $tier->id]);

        $this->enter($visitor)->assertJsonPath('data.price', 10);
        $this->enter($visitor)->assertJsonPath('data.price', 8)->assertJsonPath('data.discount_amount', 12); // 20 → 10 → 8
    }

    public function test_gear_can_be_given_later_the_same_day(): void
    {
        $visitor = $this->adult(['client_tier_id' => ClientTier::create(['name' => 'VIP', 'discount_percent' => 0, 'rental_discount_percent' => 50])->id]);
        $this->enter($visitor)->assertCreated();
        $visit = VisitorVisit::sole();

        $this->actingAs($this->staff)->post('/admin/rentals/add', [
            'visitor_visit_id' => $visit->id, 'rental_item_id' => $this->chalk->id, 'quantity' => 1, 'payment_method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $visit->refresh();
        $this->assertSame(['1.00', '21.00'], [$visit->rental_price, $visit->total_price]);
    }

    public function test_admin_manages_gear_statuses_rewards_and_gives_a_status(): void
    {
        $this->actingAs($this->admin)->post('/admin/rentals/items', ['name' => 'Harness', 'price' => 3, 'is_returnable' => 1, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/admin/loyalty/tiers', ['name' => 'VIP', 'color' => '#ffcc00', 'discount_percent' => 50, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/admin/loyalty/rewards', ['name' => 'Every 20th', 'every_visits' => 20, 'type' => 'free_entry', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/admin/loyalty/rewards', ['name' => 'Bad', 'every_visits' => 20, 'type' => 'discount'])->assertSessionHasErrors('value');
        $this->actingAs($this->admin)->post('/admin/loyalty/rewards', ['name' => 'Bad', 'every_visits' => 1, 'type' => 'free_entry'])->assertSessionHasErrors('every_visits');

        $this->assertDatabaseHas('rental_items', ['name' => 'Harness', 'is_returnable' => true, 'has_sizes' => false]);
        $this->actingAs($this->admin)->get('/admin/loyalty')->assertOk()->assertSee('VIP')->assertSee('Every 20th');

        $visitor = $this->adult();
        $tier = ClientTier::where('name', 'VIP')->first();
        $this->actingAs($this->admin)->post("/admin/visitors/{$visitor->id}/status", ['client_tier_id' => $tier->id, 'client_tier_until' => '2026-12-31'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([$tier->id, '2026-12-31'], [$visitor->fresh()->client_tier_id, $visitor->fresh()->client_tier_until->toDateString()]);
        $this->actingAs($this->admin)->get("/admin/visitors?status={$tier->id}")->assertOk()->assertSee($visitor->full_name);
    }

    public function test_workers_cannot_change_prices_or_statuses(): void
    {
        $visitor = $this->adult();

        $this->actingAs($this->staff)->post('/admin/rentals/items', ['name' => 'X', 'price' => 1])->assertForbidden();
        $this->actingAs($this->staff)->get('/admin/loyalty')->assertForbidden();
        $this->actingAs($this->staff)->post("/admin/visitors/{$visitor->id}/status", ['client_tier_id' => null])->assertForbidden();
        $this->actingAs($this->staff)->get('/admin/rentals')->assertOk(); // but they see and take back the gear
    }
}
