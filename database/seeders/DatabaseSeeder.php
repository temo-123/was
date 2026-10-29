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
            PermissionSeeder::class, // permissions from config/permissions.php
            RoleSeeder::class,       // roles admin / worker / user + their default permissions
            PricingSeeder::class,    // price list
        ]);
    }
}
