<?php

namespace Tests\Feature;

use Database\Seeders\PricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_load(): void
    {
        $this->seed(PricingSeeder::class);

        $this->get('/')->assertOk()->assertSee('150');
        $this->get('/terms')->assertOk();
        $this->get('/privacy')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->getJson('/api/v1/prices')->assertOk()->assertJsonCount(22, 'prices');
    }
}
