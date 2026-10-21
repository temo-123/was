<?php

namespace Tests\Feature;

use App\Models\TicketType;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorSubscription;
use Database\Seeders\PricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EntryTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSeeder::class);
        $this->staff = User::factory()->create()->assignRole('worker');
        Carbon::setTestNow('2026-10-20 19:15:00'); // Tuesday evening
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function ticket(string $code): int
    {
        return TicketType::where('code', $code)->value('id');
    }

    public function test_new_visitor_is_created_and_charged_by_age_and_time(): void
    {
        $response = $this->actingAs($this->staff)->postJson('/api/v1/visits', [
            'visitor' => [
                'first_name' => 'Nino',
                'last_name' => 'Beridze',
                'personal_id' => '01001012345',
                'birth_date' => '1995-05-10',
                'phone' => '599 12 34 56',
            ],
            'terms_accepted' => true,
            'ticket_type_id' => $this->ticket('single'),
            'payment_method' => 'card',
        ]);

        $response->assertCreated()->assertJsonPath('data.price', 20)->assertJsonPath('data.payment_method', 'card');
        $this->assertDatabaseHas('visitors', ['personal_id' => '01001012345', 'terms_accepted' => true]);
        $this->assertDatabaseCount('visitor_visits', 1);
    }

    public function test_new_visitor_must_accept_terms(): void
    {
        $this->actingAs($this->staff)->postJson('/api/v1/visits', [
            'visitor' => ['first_name' => 'A', 'last_name' => 'B', 'personal_id' => '123456', 'birth_date' => '1990-01-01', 'phone' => '599123456'],
            'ticket_type_id' => $this->ticket('single'),
        ])->assertUnprocessable()->assertJsonValidationErrors('terms_accepted');

        $this->assertDatabaseCount('visitors', 0);
    }

    public function test_existing_visitor_gets_entry_and_double_click_is_blocked(): void
    {
        $visitor = Visitor::factory()->create(['birth_date' => '2014-01-01']); // 12 years -> teens

        $payload = ['visitor_id' => $visitor->id, 'ticket_type_id' => $this->ticket('single')];
        $this->actingAs($this->staff)->postJson('/api/v1/visits', $payload)->assertCreated()->assertJsonPath('data.price', 15);
        $this->actingAs($this->staff)->postJson('/api/v1/visits', $payload)->assertUnprocessable();
    }

    public function test_subscription_is_sold_and_then_used(): void
    {
        $visitor = Visitor::factory()->create(['birth_date' => '1990-01-01']);

        $this->actingAs($this->staff)->postJson('/api/v1/visits', [
            'visitor_id' => $visitor->id,
            'ticket_type_id' => $this->ticket('monthly'),
            'payment_method' => 'cash',
        ])->assertCreated();

        $subscription = VisitorSubscription::firstOrFail();
        $this->assertEquals(150, $subscription->price);
        $this->assertEquals('2026-11-18', $subscription->ends_on->toDateString());

        // Next morning the evening pass covers the entry.
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->actingAs($this->staff)->getJson('/api/v1/pricing/quote?visitor_id='.$visitor->id.'&ticket_type_id='.$this->ticket('single'))
            ->assertOk()->assertJsonPath('amount', 0)->assertJsonPath('subscription.id', $subscription->id);

        $this->actingAs($this->staff)->postJson('/api/v1/visits', [
            'visitor_id' => $visitor->id,
            'ticket_type_id' => $this->ticket('single'),
        ])->assertCreated()->assertJsonPath('data.payment_method', 'subscription')->assertJsonPath('data.price', 0);

        $this->assertEquals(2, $subscription->fresh()->visits_used);
    }

    public function test_kid_in_the_evening_is_rejected_unless_price_is_overridden(): void
    {
        $kid = Visitor::factory()->create(['birth_date' => '2019-01-01']);
        $payload = ['visitor_id' => $kid->id, 'ticket_type_id' => $this->ticket('single')];

        $this->actingAs($this->staff)->postJson('/api/v1/visits', $payload)->assertUnprocessable()->assertJsonValidationErrors('ticket_type_id');

        // Workers may not override prices...
        $this->actingAs($this->staff)->postJson('/api/v1/visits', $payload + ['price_override' => 5])->assertUnprocessable();

        // ...admins may.
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->postJson('/api/v1/visits', $payload + ['price_override' => 5])
            ->assertCreated()->assertJsonPath('data.price', 5)->assertJsonPath('data.price_overridden', true);
    }

    public function test_search_finds_visitor_by_name_and_id(): void
    {
        Visitor::factory()->create(['first_name' => 'Giorgi', 'last_name' => 'Kapanadze', 'personal_id' => '61001055555']);
        Visitor::factory()->create(['first_name' => 'Ana', 'last_name' => 'Lomidze']);

        $this->actingAs($this->staff)->getJson('/api/v1/visitors/search?q=giorgi kapa')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->staff)->getJson('/api/v1/visitors/search?q=6100105')->assertOk()->assertJsonPath('data.0.last_name', 'Kapanadze');
    }

    public function test_visitor_fields_are_validated_and_normalized(): void
    {
        $base = [
            'first_name' => 'ნინო', 'last_name' => 'ბერიძე', 'personal_id' => '01001012345',
            'birth_date' => '1995-05-10', 'phone' => '599123456', 'email' => 'nino@gmail.com',
        ];
        $post = fn (array $visitor) => $this->actingAs($this->staff)->postJson('/api/v1/visits', [
            'visitor' => $visitor, 'terms_accepted' => true, 'ticket_type_id' => $this->ticket('single'),
        ]);

        $post(['personal_id' => '1234567890'] + $base)->assertJsonValidationErrors('visitor.personal_id');   // 10 digits
        $post(['personal_id' => '0100101234a'] + $base)->assertJsonValidationErrors('visitor.personal_id');  // letter
        $post(['phone' => '599abc456'] + $base)->assertJsonValidationErrors('visitor.phone');
        $post(['phone' => '59912'] + $base)->assertJsonValidationErrors('visitor.phone');                    // too short
        $post(['email' => 'nino@gmail'] + $base)->assertJsonValidationErrors('visitor.email');               // no domain
        $post(['email' => 'nino gmail.com'] + $base)->assertJsonValidationErrors('visitor.email');
        $post(['first_name' => '123'] + $base)->assertJsonValidationErrors('visitor.first_name');
        $post(['birth_date' => now()->addDay()->toDateString()] + $base)->assertJsonValidationErrors('visitor.birth_date');
        $this->assertDatabaseCount('visitors', 0);

        // Spaces / dashes typed by staff are removed, email lower-cased.
        $post(['phone' => '599 12-34 56', 'personal_id' => '010 0101 2345', 'email' => ' Nino@Gmail.com '] + $base)->assertCreated();
        $this->assertDatabaseHas('visitors', ['personal_id' => '01001012345', 'phone' => '599123456', 'email' => 'nino@gmail.com']);
    }

    public function test_quote_explains_when_the_gym_is_closed(): void
    {
        Carbon::setTestNow('2026-10-21 00:06:00');
        $visitor = Visitor::factory()->create(['birth_date' => '1990-01-01']);

        $this->actingAs($this->staff)->getJson('/api/v1/pricing/quote?visitor_id='.$visitor->id.'&ticket_type_id='.$this->ticket('single'))
            ->assertOk()
            ->assertJsonPath('quote.available', false)
            ->assertJsonPath('quote.message', 'The gym is closed at 00:06. Working hours: 08:00–23:00.');
    }

    public function test_visitor_can_be_saved_without_an_entrance(): void
    {
        $data = ['first_name' => 'ნინო', 'last_name' => 'ბერიძე', 'personal_id' => '01001012345', 'birth_date' => '1995-05-10', 'phone' => '599 12 34 56'];

        $this->actingAs($this->staff)->postJson('/api/v1/visitors', $data)->assertJsonValidationErrors('terms_accepted');

        $this->actingAs($this->staff)->postJson('/api/v1/visitors', $data + ['terms_accepted' => true])
            ->assertCreated()->assertJsonPath('data.phone', '599123456')->assertJsonPath('data.terms_accepted', true);
        $this->assertDatabaseCount('visitor_visits', 0);
    }
}
