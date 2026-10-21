<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorUser;
use App\Services\VisitorLinker;
use Database\Seeders\PricingSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSeeder::class);
    }

    public function test_registering_with_existing_personal_id_links_the_visitor(): void
    {
        $visitor = Visitor::factory()->create(['personal_id' => '01019000001']);

        $this->post('/register', [
            'name' => 'Luka',
            'email' => 'luka@example.com',
            'personal_id' => '01019000001',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'terms' => '1',
        ])->assertRedirect('/profile');

        $user = User::where('email', 'luka@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertTrue($user->visitor->is($visitor));
    }

    public function test_unverified_email_does_not_link_but_verified_does(): void
    {
        $visitor = Visitor::factory()->create(['email' => 'mari@example.com']);
        $user = User::factory()->unverified()->create(['email' => 'mari@example.com']);

        app(VisitorLinker::class)->linkUserToMatchingVisitor($user);
        $this->assertDatabaseCount('visitor_user', 0);

        $user->markEmailAsVerified();
        event(new Verified($user));
        $this->assertDatabaseHas('visitor_user', ['visitor_id' => $visitor->id, 'user_id' => $user->id, 'linked_via' => 'email']);
    }

    public function test_new_visitor_is_linked_to_existing_user_with_same_personal_id(): void
    {
        $user = User::factory()->create(['personal_id' => '01019000002'])->assignRole('user');
        $staff = User::factory()->create()->assignRole('worker');

        $this->actingAs($staff)->post('/admin/visitors', [
            'first_name' => 'Saba', 'last_name' => 'Gelashvili', 'personal_id' => '01019000002',
            'birth_date' => '2000-02-02', 'phone' => '599000000', 'terms_accepted' => '1',
        ])->assertRedirect();

        $this->assertEquals($user->id, VisitorUser::first()?->user_id);
    }
}
