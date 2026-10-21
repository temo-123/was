<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Test accounts (password "secret"): admin@test.loc, worker@test.loc, user@test.loc
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,     // roles admin / worker / user + permissions
            PricingSeeder::class,  // price list
            UserSeeder::class,     // 3 test accounts
        ]);

        // Example data — not for production.
        if (! app()->isProduction()) {
            $this->call([
                VisitorSeeder::class, // ~50 Georgian visitors
                VisitSeeder::class,   // ~500 visits
            ]);
        }
    }
}
