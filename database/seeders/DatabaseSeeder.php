<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Create the first admin afterwards with `php artisan app:create-admin`.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,     // roles admin / worker / user + permissions
            PricingSeeder::class,  // price list
        ]);
    }
}
