<?php

namespace Tests\Feature;

use App\Models\AgeGroup;
use App\Models\Permission;
use App\Models\TicketType;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorCategory;
use Database\Seeders\PricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSeeder::class);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->getJson('/api/v1/visitors')->assertUnauthorized();
    }

    public function test_users_cannot_open_the_admin_panel(): void
    {
        $member = User::factory()->create()->assignRole('user');

        $this->actingAs($member)->get('/admin')->assertForbidden();
        $this->actingAs($member)->get('/profile')->assertOk();
    }

    public function test_worker_can_work_with_visitors_but_not_users_or_prices(): void
    {
        $staff = User::factory()->create()->assignRole('worker');

        foreach (['/admin', '/admin/entry', '/admin/visitors', '/admin/visits', '/admin/subscriptions', '/admin/pricing'] as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }
        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
        $this->actingAs($staff)->get('/admin/pricing/settings')->assertForbidden();
        $this->actingAs($staff)->put('/admin/pricing', ['prices' => []])->assertForbidden();
    }

    public function test_direct_user_permission_works_without_role(): void
    {
        $user = User::factory()->create()->assignRole('worker');
        $this->actingAs($user)->get('/admin/users')->assertForbidden();

        $user->syncDirectPermissions([Permission::where('name', 'users.view')->value('id')]);
        $this->actingAs($user->fresh())->get('/admin/users')->assertOk();
    }

    public function test_admin_sees_every_admin_page(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $visitor = Visitor::factory()->create();

        foreach ([
            '/admin', '/admin/entry', '/admin/visitors', '/admin/visitors/create', "/admin/visitors/{$visitor->id}",
            "/admin/visitors/{$visitor->id}/edit", '/admin/visits', '/admin/visits?export=csv', '/admin/subscriptions',
            '/admin/pricing', '/admin/pricing/settings', '/admin/coefficients/create', '/admin/users', '/admin/users/create',
            "/admin/users/{$admin->id}/edit", '/admin/roles', '/admin/roles/create', '/admin/permissions', '/admin/activity',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_disabled_user_is_logged_out(): void
    {
        $staff = User::factory()->create(['is_active' => false])->assignRole('worker');

        $this->actingAs($staff)->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_can_save_price_matrix(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $ids = [
            'time_slot_id' => TimeSlot::where('code', 'evening')->value('id'),
            'ticket_type_id' => TicketType::where('code', 'single')->value('id'),
            'age_group_id' => AgeGroup::where('code', 'adults')->value('id'),
            'visitor_category_id' => VisitorCategory::where('code', 'member')->value('id'),
        ];
        $key = implode('-', $ids);

        $this->actingAs($admin)->put('/admin/pricing', ['prices' => [$key => '12.50']])->assertRedirect();
        $this->assertDatabaseHas('price_rules', $ids + ['price' => 12.50, 'is_active' => true]);

        // An empty cell switches the price off.
        $this->actingAs($admin)->put('/admin/pricing', ['prices' => [$key => '']])->assertRedirect();
        $this->assertDatabaseHas('price_rules', $ids + ['is_active' => false]);
    }
}
