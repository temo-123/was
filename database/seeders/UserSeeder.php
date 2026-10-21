<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Test accounts, one per role. Password for all: "secret". */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['admin@test.loc', 'Admin', 'admin'],
            ['worker@test.loc', 'Worker', 'worker'],
            ['user@test.loc', 'User', 'user'],
        ];

        foreach ($accounts as [$email, $name, $role]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'secret',
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            $user->syncRoles([]);
            $user->assignRole($role);
        }
    }
}
